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
use App\Services\Media\PropertyMediaLibrary;
use App\Models\User;
use App\Models\VerificationItem;
use App\Models\VerificationPassport;
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
        $property->loadMissing(['media', 'owners', 'authorities']);
        $blockers = [];

        if ($property->purpose === null) {
            $blockers[] = 'Listing purpose (sale or lease) is not set.';
        } elseif ($property->priceBasis() !== PriceBasis::Total && $property->priceBasis()->purpose() !== $property->purpose) {
            $blockers[] = 'The price basis does not match a '.strtolower($property->purpose->label()).' listing.';
        }

        if (blank($property->title) || (blank($property->summary) && blank($property->description))) {
            $blockers[] = 'Title and a summary or description are required.';
        }

        if ($property->county_id === null || $property->locality_id === null) {
            $blockers[] = 'County and locality are not set.';
        }

        if ((float) $property->acreage <= 0) {
            $blockers[] = 'Acreage is not recorded.';
        } elseif ($property->offered_acreage !== null && (float) $property->offered_acreage > $property->remainingAcreage() + 0.0001) {
            $blockers[] = 'The acreage offered is larger than the land still held.';
        }

        if ($property->price_amount === null) {
            $blockers[] = $property->isLease() ? 'Asking rent is not recorded.' : 'Asking price is not recorded.';
        }

        $hasPublicPhoto = $property->media->contains(
            fn (PropertyMedia $media): bool => PropertyMediaLibrary::isPublicDerivative($property, $media),
        );

        if (! $hasPublicPhoto) {
            $blockers[] = 'At least one public photograph is required.';
        }

        if ($property->owners->isNotEmpty()) {
            if (! $property->owners->contains(fn ($owner): bool => $owner->status === OwnerLinkStatus::Verified)) {
                $blockers[] = 'No ownership or authority relationship has been verified.';
            }

            $authorised = $property->purpose !== null && $property->authorities->contains(
                fn (MarketingAuthority $authority): bool => $authority->permitsMarketing($property->purpose),
            );

            if (! $authorised) {
                $blockers[] = 'No active marketing authority covers this '.strtolower($property->purpose?->label() ?? 'listing').'.';
            }
        }

        $conflict = $this->parcelConflict($property);

        if ($conflict !== null) {
            $blockers[] = 'Parcel '.$property->parcel_identifier.' is already marketed as '.$conflict->reference.'.';
        }

        return $blockers;
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
