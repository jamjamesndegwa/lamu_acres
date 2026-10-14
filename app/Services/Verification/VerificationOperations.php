<?php

namespace App\Services\Verification;

use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Enums\VerificationLevel;
use App\Enums\VerificationOverride;
use App\Enums\VerificationQueue;
use App\Models\Property;
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
     * @return array<int, string>
     */
    public function blockers(Property $property): array
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
