<?php

namespace App\Services\Verification;

use App\Enums\OwnerLinkStatus;
use App\Enums\PartnerCapability;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Enums\VerificationLevel;
use App\Enums\VerificationOverride;
use App\Enums\VerificationQueue;
use App\Models\MarketingAuthority;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\PropertyMedia;
use App\Models\User;
use App\Models\VerificationItem;
use App\Models\VerificationPassport;
use App\Notifications\WorkAssignedNotification;
use App\Services\Access\CapabilityReviewer;
use App\Services\Broker\DuplicateListingDetector;
use App\Services\Media\PropertyMediaLibrary;
use App\Services\Seller\SellerPortal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VerificationOperations
{
    public function __construct(private VerificationDesk $desk) {}

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function summary(): array
    {
        return [
            $this->row(VerificationQueue::New, $this->newReviews()->count()),
            $this->row(VerificationQueue::Missing, $this->missingDocuments()->count()),
            $this->row(VerificationQueue::Exceptions, $this->exceptions()->count()),
            $this->row(VerificationQueue::Expiring, $this->expiringChecks()->count()),
            $this->row(VerificationQueue::Completed, $this->completedReviews()->count()),
        ];
    }

    public function newReviews(): Builder
    {
        return Property::query()
            ->whereHas('passport')
            ->whereDoesntHave('verificationReviews');
    }

    public function missingDocuments(): Builder
    {
        return Property::query()->whereHas(
            'verificationItems',
            fn (Builder $query) => $query->where('state', VerificationItemState::NotSupplied),
        );
    }

    public function exceptions(): Builder
    {
        $critical = array_map(
            fn (VerificationCategory $category) => $category->value,
            array_filter(VerificationCategory::cases(), fn (VerificationCategory $category) => $category->critical()),
        );

        return Property::query()->where(function (Builder $query) use ($critical): void {
            $query->whereHas('passport', function (Builder $passport): void {
                $passport->whereIn('override', [
                    VerificationOverride::Flagged->value,
                    VerificationOverride::Expired->value,
                    VerificationOverride::Suspended->value,
                ])->orWhereNotNull('critical_exception');
            })->orWhereHas('verificationItems', function (Builder $items) use ($critical): void {
                $items->whereIn('category', $critical)
                    ->whereIn('state', [VerificationItemState::Failed->value, VerificationItemState::Expired->value]);
            });
        });
    }

    public function expiringChecks(): Builder
    {
        $until = now()->addDays(30);

        return VerificationItem::query()
            ->where('state', VerificationItemState::Verified)
            ->where(function (Builder $query) use ($until): void {
                $query->whereBetween('expires_at', [now(), $until])
                    ->orWhereBetween('review_due_at', [now(), $until]);
            });
    }

    public function completedReviews(): Builder
    {
        $critical = array_map(
            fn (VerificationCategory $category) => $category->value,
            array_filter(VerificationCategory::cases(), fn (VerificationCategory $category) => $category->critical()),
        );

        return Property::query()
            ->whereHas('passport', function (Builder $passport): void {
                $passport->whereIn('level', [
                    VerificationLevel::OwnershipChecked->value,
                    VerificationLevel::Enhanced->value,
                ])->where('override', VerificationOverride::None->value)
                    ->whereNull('critical_exception');
            })
            ->whereDoesntHave('verificationItems', function (Builder $items) use ($critical): void {
                $items->whereIn('category', $critical)
                    ->whereNotIn('state', [
                        VerificationItemState::Verified->value,
                        VerificationItemState::NotApplicable->value,
                    ]);
            });
    }

    public function assign(VerificationPassport $passport, User $assignee, User $actor, Carbon $deadline): VerificationPassport
    {
        if (! $assignee->hasPermission('staff.verification')) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Assign the review to a verification officer.',
            ]);
        }

        if ($deadline->lte(now())) {
            throw ValidationException::withMessages([
                'deadline_at' => 'The review deadline must be in the future.',
            ]);
        }

        $changed = (int) $passport->assigned_to !== (int) $assignee->id || $passport->deadline_at?->getTimestamp() !== $deadline->getTimestamp();
        $passport->assigned_to = $assignee->id;
        $passport->assigned_by = $actor->id;
        $passport->deadline_at = $deadline;
        $passport->save();

        $passport->property->verificationReviews()->create([
            'actor_id' => $actor->id,
            'decision' => 'assigned',
            'reason' => 'Assigned to '.$assignee->name.' with a deadline of '.$deadline->toDayDateTimeString().'.',
        ]);

        if ($changed) {
            $assignee->notify(new WorkAssignedNotification('Verification review', $passport->id, route('staff.verification.show', $passport->property)));
        }

        return $passport;
    }

    public function decide(VerificationItem $item, User $reviewer, VerificationItemState $state, string $reason, ?string $expiresAt = null, ?string $reviewDueAt = null): VerificationItem
    {
        if (blank($reason) || strlen(trim($reason)) < 8) {
            throw ValidationException::withMessages([
                'reason' => 'Record the reason for this decision.',
            ]);
        }

        $payload = [
            'state' => $state->value,
            'evidence_note' => trim($reason),
            'notes' => trim($reason),
        ];

        if ($expiresAt !== null) {
            $payload['expires_at'] = $expiresAt;
        }

        if ($reviewDueAt !== null) {
            $payload['review_due_at'] = $reviewDueAt;
        }

        return $this->desk->review($item, $reviewer, $payload);
    }

    /**
     * Everything that stops a property from being published: passport checks plus
     * listing completeness, ownership, marketing authority and parcel conflicts.
     *
     * @return array<int, string>
     */
    public function blockers(Property $property): array
    {
        if ($property->isStaffIntakeListing()) {
            return $this->staffIntakeBlockers($property);
        }

        return array_merge($this->passportBlockers($property), $this->listingBlockers($property));
    }

    /**
     * Staff-registered listings only need complete listing details and public photos before publication.
     *
     * @return array<int, string>
     */
    public function staffIntakeBlockers(Property $property): array
    {
        $groups = $this->listingBlockerGroups($property);

        return array_merge($groups['details'], $groups['photos']);
    }

    /**
     * @return array<int, string>
     */
    public function listingBlockers(Property $property): array
    {
        return array_merge(...array_values($this->listingBlockerGroups($property)));
    }

    /** @return array<string, list<string>> */
    public function listingBlockerGroups(Property $property): array
    {
        $property->loadMissing(['media', 'owners', 'authorities']);
        $blockers = ['details' => [], 'photos' => [], 'owners' => [], 'authority' => [], 'parcel' => []];

        if ($property->purpose === null) {
            $blockers['details'][] = 'Listing purpose (sale or lease) is not set.';
        } elseif ($property->priceBasis() !== PriceBasis::Total && $property->priceBasis()->purpose() !== $property->purpose) {
            $blockers['details'][] = 'The price basis does not match a '.strtolower($property->purpose->label()).' listing.';
        }

        if (blank($property->title) || (blank($property->summary) && blank($property->description))) {
            $blockers['details'][] = 'Title and a summary or description are required.';
        }

        if ($property->county_id === null || $property->locality_id === null) {
            $blockers['details'][] = 'County and locality are not set.';
        }

        if ((float) $property->acreage <= 0) {
            $blockers['details'][] = 'Acreage is not recorded.';
        } elseif ($property->offered_acreage !== null && (float) $property->offered_acreage > $property->remainingAcreage() + 0.0001) {
            $blockers['details'][] = 'The acreage offered is larger than the land still held.';
        }

        if ($property->price_amount === null) {
            $blockers['details'][] = $property->isLease() ? 'Asking rent is not recorded.' : 'Asking price is not recorded.';
        }

        $hasPublicPhoto = $property->media->contains(
            fn (PropertyMedia $media): bool => PropertyMediaLibrary::isPublicDerivative($property, $media),
        );

        if (! $hasPublicPhoto) {
            $blockers['photos'][] = 'At least one public photograph is required.';
        }

        if ($property->isStaffIntakeListing()) {
            return $blockers;
        }

        if (! $property->isLamuAcresInventoryListing()) {
            if (! $property->owners->contains(fn ($owner): bool => $owner->status === OwnerLinkStatus::Verified)) {
                $blockers['owners'][] = 'Link and verify an owner or authorized representative on the Owners tab.';
            }
            foreach ($property->owners as $owner) {
                if ($owner->status === OwnerLinkStatus::Verified) {
                    array_push($blockers['owners'], ...app(SellerPortal::class)->ownerIdentityBlockers($owner));
                }
            }
            $verifiedOwnerIds = $property->owners->where('status', OwnerLinkStatus::Verified)->pluck('user_id')->all();
            $authorised = $property->purpose !== null && $property->authorities->contains(
                fn (MarketingAuthority $authority): bool => in_array($authority->seller_id, $verifiedOwnerIds, true) && $authority->permitsMarketing($property->purpose),
            );
            if (! $authorised) {
                $blockers['authority'][] = 'Approve a current marketing authority from a verified owner/representative covering this '.strtolower($property->purpose?->label() ?? 'listing').'.';
            }
        }
        $submitter = $property->submitter;
        if ($submitter !== null && ! $property->owners->contains('user_id', $submitter->id)
            && (! $submitter->hasPermission('workspace.broker') || ! app(CapabilityReviewer::class)->hasCurrentCapability($submitter, PartnerCapability::Broker))) {
            $blockers['authority'][] = 'The submitting broker must have current, staff-verified professional credentials.';
        }
        if (! $property->isStaffIntakeListing()) {
            foreach (app(SellerPortal::class)->documentRequirements($property) as $requirement) {
                if (! in_array($requirement['requirement'], [SellerPortal::REQUIRED, SellerPortal::CONDITIONAL], true)) {
                    continue;
                }
                $lastReview = $property->ownershipDocuments()->where('document_type', $requirement['type'])
                    ->whereNotNull('reviewed_at')->orderByDesc('reviewed_at')->orderByDesc('id')->first();
                $conflictingReview = $lastReview !== null && (in_array($lastReview->state, [VerificationItemState::Failed, VerificationItemState::Expired], true)
                    || ($lastReview->state === VerificationItemState::Verified && $lastReview->expires_at !== null && $lastReview->expires_at->isPast()));
                if ($conflictingReview || in_array($requirement['state'], [VerificationItemState::Failed->label(), VerificationItemState::Expired->label()], true)) {
                    $blockers['owners'][] = $requirement['label'].' has conflicting or expired evidence. Staff must review the replacement document before publication.';
                }
            }
        }
        $conflict = $this->parcelConflict($property);
        if ($conflict !== null && ! $this->duplicateReviewIsClear($property)) {
            $blockers['parcel'][] = 'Possible duplicate parcel. Open listing review and record whether shared marketing is authorized; this is not an automatic rejection.';
        }

        return $blockers;
    }

    public function recordDuplicateReview(User $staff, Property $property, string $outcome, string $reason): void
    {
        abort_unless(! $staff->isSuspended() && $staff->hasPermission('staff.properties') && $staff->id !== $property->submitted_by, 403);
        if (! in_array($outcome, ['shared_marketing_authorized', 'hold'], true) || strlen(trim($reason)) < 20) {
            throw ValidationException::withMessages(['duplicate_review' => 'Choose an outcome and record at least 20 characters explaining the authority and parcel comparison.']);
        }
        PropertyAudit::query()->create([
            'property_id' => $property->id, 'actor_id' => $staff->id, 'action' => 'duplicate_parcel_reviewed',
            'changes' => ['signature' => $this->duplicateSignature($property), 'outcome' => $outcome, 'reason' => trim($reason)],
        ]);
    }

    private function duplicateSignature(Property $property): string
    {
        $detector = app(DuplicateListingDetector::class);
        $parcel = $detector->normalizeParcel($property->parcel_identifier);
        $matches = $detector->staffMatches($property)->filter(fn (Property $match): bool => $detector->normalizeParcel($match->parcel_identifier) === $parcel
            && in_array($match->status, [PropertyStatus::Published, PropertyStatus::Reserved, PropertyStatus::Leased], true)
            && ! in_array($match->id, array_filter([$property->id, $property->parent_id]), true)
            && ! in_array($match->parent_id, array_filter([$property->id, $property->parent_id]), true))
            ->pluck('id')->sort()->values()->all();

        return hash('sha256', json_encode([$parcel, $matches], JSON_THROW_ON_ERROR));
    }

    private function duplicateReviewIsClear(Property $property): bool
    {
        $review = $property->audits()->where('action', 'duplicate_parcel_reviewed')->latest('id')->first();

        return $review !== null && ($review->changes['outcome'] ?? null) === 'shared_marketing_authorized'
            && ($review->changes['signature'] ?? null) === $this->duplicateSignature($property);
    }

    /**
     * @param  array<int, array<string, mixed>>  $requirements
     * @return list<array{key: string, title: string, instruction: string, issues: list<string>, complete: bool, tab: string, action: string}>
     */
    public function approvalSteps(Property $property, array $requirements): array
    {
        $groups = $this->listingBlockerGroups($property);

        if ($property->isStaffIntakeListing()) {
            return $this->staffIntakeApprovalSteps($groups);
        }

        $documentIssues = [];

        foreach ($requirements as $requirement) {
            if (in_array($requirement['requirement'], ['required', 'conditional'], true)
                && ! in_array($requirement['state'], [VerificationItemState::Verified->label(), VerificationItemState::NotApplicable->label()], true)) {
                $documentIssues[] = $requirement['label'].': '.$requirement['state'].'.';
            }
        }

        $steps = [
            ['details', 'Check listing details', 'Confirm the parcel, location, acreage, asking price and description. Resolve duplicate parcel listings before publication.', array_merge($groups['details'], $groups['parcel']), 'overview', 'Edit listing details'],
            ['documents', 'Review seller documents', 'Open the private files and approve the documents you have checked. Review required and conditional documents; optional files can be reviewed when supplied.', $documentIssues, 'owners', 'Review uploaded documents'],
            ['owners', 'Verify the ownership relationship', 'Check the identity and authority of the linked owner or representative, then record the ownership verification.', $groups['owners'], 'owners', 'Verify ownership'],
            ['authority', 'Confirm marketing authority', 'Confirm that an active, unexpired authority covers the sale or lease being advertised. A signed attachment also needs an authority decision.', $groups['authority'], 'marketing', 'Review marketing authority'],
            ['photos', 'Check public photographs', 'Review the gallery and cover image. At least one public photograph is required.', $groups['photos'], 'media', 'Review photographs'],
            ['verification', 'Complete the verification passport', 'Record a decision for every critical check and resolve overrides or critical exceptions. Document approvals do not automatically verify these checks.', $this->passportBlockers($property), 'verification', 'Complete verification checks'],
        ];

        return array_map(fn (array $step): array => [
            'key' => $step[0], 'title' => $step[1], 'instruction' => $step[2],
            'issues' => $step[3], 'complete' => $step[3] === [], 'tab' => $step[4], 'action' => $step[5],
        ], $steps);
    }

    /**
     * @param  array<string, list<string>>  $groups
     * @return list<array{key: string, title: string, instruction: string, issues: list<string>, complete: bool, tab: string, action: string}>
     */
    private function staffIntakeApprovalSteps(array $groups): array
    {
        $steps = [
            ['details', 'Check listing details', 'Confirm the parcel, location, acreage, asking price and description. Seller document, ownership and passport checks do not apply to staff-registered land.', $groups['details'], 'overview', 'Edit listing details'],
            ['photos', 'Check public photographs', 'Upload at least one public photograph for the land directory.', $groups['photos'], 'media', 'Review photographs'],
        ];

        return array_map(fn (array $step): array => [
            'key' => $step[0], 'title' => $step[1], 'instruction' => $step[2],
            'issues' => $step[3], 'complete' => $step[3] === [], 'tab' => $step[4], 'action' => $step[5],
        ], $steps);
    }

    /**
     * Another live listing for the same registered parcel, outside this parcel's own portions.
     */
    public function parcelConflict(Property $property): ?Property
    {
        if (blank($property->parcel_identifier)) {
            return null;
        }

        $family = array_filter([$property->id, $property->parent_id]);
        $detector = app(DuplicateListingDetector::class);
        $parcel = $detector->normalizeParcel($property->parcel_identifier);

        return $detector->staffMatches($property)->first(fn (Property $match): bool => $detector->normalizeParcel($match->parcel_identifier) === $parcel
            && in_array($match->status, [PropertyStatus::Published, PropertyStatus::Reserved, PropertyStatus::Leased], true)
            && ! in_array($match->id, $family, true) && ! in_array($match->parent_id, $family, true));
    }

    /**
     * @return array<int, string>
     */
    private function passportBlockers(Property $property): array
    {
        if ($property->isStaffIntakeListing()) {
            return [];
        }

        $property->loadMissing(['passport', 'verificationItems']);
        $blockers = [];

        if ($property->passport === null || $property->verificationItems->isEmpty()) {
            return ['Verification passport has not been started.'];
        }

        if ($property->passport->override !== VerificationOverride::None) {
            $blockers[] = 'Verification override: '.$property->passport->override->label().'.';
        }

        if (filled($property->passport->critical_exception)) {
            $blockers[] = 'Critical exception: '.$property->passport->critical_exception;
        }

        foreach ($property->verificationItems as $item) {
            if (! $item->category->critical()) {
                continue;
            }

            if ($item->state === VerificationItemState::Verified && (($item->expires_at !== null && $item->expires_at->isPast())
                || ($item->review_due_at !== null && $item->review_due_at->isPast()))) {
                $blockers[] = $item->category->label().' is expired or due for review. Update the critical verification check.';
            }
            if (! in_array($item->state, [VerificationItemState::Verified, VerificationItemState::NotApplicable], true)) {
                $blockers[] = $item->category->label().' is '.$item->state->label().'.';
            }
        }

        return $blockers;
    }

    public function assertClear(Property $property): void
    {
        $blockers = $this->blockers($property);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['listing' => $blockers]);
        }
    }

    /**
     * @return array{key: string, label: string, count: int}
     */
    private function row(VerificationQueue $queue, int $count): array
    {
        return [
            'key' => $queue->value,
            'label' => $queue->label(),
            'count' => $count,
        ];
    }
}
