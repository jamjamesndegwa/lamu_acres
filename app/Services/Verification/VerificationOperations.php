<?php

namespace App\Services\Verification;

use App\Enums\OwnerLinkStatus;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Enums\VerificationLevel;
use App\Enums\VerificationOverride;
use App\Enums\VerificationQueue;
use App\Models\MarketingAuthority;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use App\Models\VerificationItem;
use App\Models\VerificationPassport;
use App\Services\Media\PropertyMediaLibrary;
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

        $passport->assigned_to = $assignee->id;
        $passport->assigned_by = $actor->id;
        $passport->deadline_at = $deadline;
        $passport->save();

        $passport->property->verificationReviews()->create([
            'actor_id' => $actor->id,
            'decision' => 'assigned',
            'reason' => 'Assigned to '.$assignee->name.' with a deadline of '.$deadline->toDayDateTimeString().'.',
        ]);

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
        return array_merge($this->passportBlockers($property), $this->listingBlockers($property));
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

        if ($property->owners->isNotEmpty()) {
            if (! $property->owners->contains(fn ($owner): bool => $owner->status === OwnerLinkStatus::Verified)) {
                $blockers['owners'][] = 'No ownership or authority relationship has been verified.';
            }

            $authorised = $property->purpose !== null && $property->authorities->contains(
                fn (MarketingAuthority $authority): bool => $authority->permitsMarketing($property->purpose),
            );

            if (! $authorised) {
                $blockers['authority'][] = 'No active marketing authority covers this '.strtolower($property->purpose?->label() ?? 'listing').'.';
            }
        }

        $conflict = $this->parcelConflict($property);

        if ($conflict !== null) {
            $blockers['parcel'][] = 'Parcel '.$property->parcel_identifier.' is already marketed as '.$conflict->reference.'.';
        }

        return $blockers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $requirements
     * @return list<array{key: string, title: string, instruction: string, issues: list<string>, complete: bool, tab: string, action: string}>
     */
    public function approvalSteps(Property $property, array $requirements): array
    {
        $groups = $this->listingBlockerGroups($property);
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
     * Another live listing for the same registered parcel, outside this parcel's own portions.
     */
    public function parcelConflict(Property $property): ?Property
    {
        if (blank($property->parcel_identifier)) {
            return null;
        }

        $family = array_filter([$property->id, $property->parent_id]);

        return Property::query()
            ->whereKeyNot($property->id)
            ->where('parcel_identifier', $property->parcel_identifier)
            ->whereIn('status', [PropertyStatus::Published, PropertyStatus::Reserved, PropertyStatus::Leased])
            ->whereNotIn('id', $family)
            ->where(fn (Builder $query) => $query->whereNull('parent_id')->orWhereNotIn('parent_id', $family))
            ->first(['id', 'reference']);
    }

    /**
     * @return array<int, string>
     */
    private function passportBlockers(Property $property): array
    {
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
