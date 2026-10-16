<?php

namespace App\Services\Research;

use App\Enums\AssignmentStatus;
use App\Enums\MessageEvent;
use App\Enums\ResearchCommitment;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\PublicationDelivery;
use App\Models\ResearchAssignment;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;
use App\Models\User;
use App\Notifications\DevelopmentAlertNotification;
use App\Notifications\ResearchPublishedNotification;
use App\Services\Communications\MessageDesk;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CommitmentDesk
{
    public function sync(?CarbonInterface $today = null): void
    {
        $today ??= now();

        foreach (ResearchCommitment::cases() as $commitment) {
            $this->ensure($commitment, $today);
            $this->ensure($commitment, $this->previous($commitment, $today));
        }
    }

    public function fulfillReport(ResearchAssignment $assignment, ResearchReport $report): void
    {
        if ($assignment->commitment->expectsImagery() || ! $report->isReleased()) {
            throw ValidationException::withMessages([
                'report' => 'Only a published report can close this assignment.',
            ]);
        }

        $this->close($assignment, $report, null);
    }

    public function fulfillImagery(ResearchAssignment $assignment, ImageryComparison $comparison): void
    {
        if (! $assignment->commitment->expectsImagery() || ! $this->usable($comparison, $assignment)) {
            throw ValidationException::withMessages([
                'comparison' => 'That comparison is not a published, usable pair for this quarter.',
            ]);
        }

        $this->close($assignment, $comparison, null);
    }

    public function afterPublication(Model $subject): void
    {
        if (! $subject instanceof DevelopmentProject || ! $subject->isReleased()) {
            return;
        }

        $watches = SubscriberWatch::query()
            ->with('user')
            ->where(function ($query) use ($subject): void {
                $query->where('development_project_id', $subject->id);

                if ($subject->locality_id !== null) {
                    $query->orWhere('locality_id', $subject->locality_id);
                }
            })
            ->get();

        foreach ($watches->unique('user_id') as $watch) {
            if (! $watch->user->hasActiveInvestorPass()) {
                continue;
            }

            if (! $this->deliver($watch->user, $subject, null, 'project:'.$subject->id.':user:'.$watch->user_id)) {
                continue;
            }
            $watch->user->notify(new DevelopmentAlertNotification($subject));
        }
    }

    private function ensure(ResearchCommitment $commitment, CarbonInterface $anchor): void
    {
        $today = now();
        [$start, $end] = $this->bounds($commitment, $anchor);

        $assignment = ResearchAssignment::query()->firstOrCreate(
            ['commitment' => $commitment->value, 'period_start' => $start->toDateString()],
            [
                'period_end' => $end->toDateString(),
                'due_on' => $end->toDateString(),
                'status' => AssignmentStatus::Scheduled,
            ],
        );

        if (in_array($assignment->status, [AssignmentStatus::Published, AssignmentStatus::Unavailable], true)) {
            return;
        }

        $status = AssignmentStatus::Scheduled;
        $note = null;

        if ($end->toDateString() < $today->toDateString()) {
            $status = AssignmentStatus::Overdue;
        } elseif ($end->toDateString() === $today->toDateString()) {
            $status = AssignmentStatus::Due;
        }

        if ($commitment->expectsImagery() && $end->toDateString() < $today->toDateString() && ! $this->periodHasUsableImagery($start, $end)) {
            $status = AssignmentStatus::Unavailable;
            $note = 'No usable published imagery was on file for this quarter. A comparison was not created.';
        }

        $assignment->update(['status' => $status, 'note' => $note]);
    }

    private function close(ResearchAssignment $assignment, Model $subject, ?string $note): void
    {
        $assignment->update([
            'status' => AssignmentStatus::Published,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'note' => $note,
            'published_at' => now(),
        ]);

        User::query()->whereHas('entitlements', function ($query): void {
            $query->where('code', 'investor_pass')->where('status', 'active')->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
        })->each(function (User $user) use ($assignment, $subject): void {
            if (! $user->hasActiveInvestorPass()) {
                return;
            }

            if (! $this->deliver($user, $subject, $assignment, 'assignment:'.$assignment->id.':user:'.$user->id)) {
                return;
            }
            if (! $subject instanceof ResearchReport) {
                $user->notify(new ResearchPublishedNotification($assignment, $subject));
            }

            if ($subject instanceof ResearchReport) {
                app(MessageDesk::class)->send($user, MessageEvent::ReportPublished, [
                    'name' => $user->name,
                    'title' => $subject->title,
                    'reference' => $subject->reference,
                    'summary' => $subject->title.' is now available.',
                    'url' => route('investor.reports.show', $subject),
                ], 'report:'.$subject->id, new ResearchPublishedNotification($assignment, $subject));
            }
        });
    }

    private function deliver(User $user, Model $subject, ?ResearchAssignment $assignment, string $key): bool
    {
        return PublicationDelivery::query()->firstOrCreate(
            ['delivery_key' => $key],
            [
                'user_id' => $user->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'research_assignment_id' => $assignment?->id,
                'delivered_at' => now(),
            ],
        )->wasRecentlyCreated;
    }

    private function usable(ImageryComparison $comparison, ResearchAssignment $assignment): bool
    {
        if (! $comparison->isReleased()) {
            return false;
        }

        $comparison->loadMissing(['beforeScene', 'afterScene']);

        return $comparison->beforeScene?->isReleased() === true
            && $comparison->afterScene?->isReleased() === true
            && $comparison->afterScene->acquired_on !== null
            && $comparison->afterScene->acquired_on->between($assignment->period_start, $assignment->period_end);
    }

    private function periodHasUsableImagery(CarbonInterface $start, CarbonInterface $end): bool
    {
        return ImageryComparison::query()->released()
            ->whereHas('beforeScene', fn ($query) => $query->released())
            ->whereHas('afterScene', fn ($query) => $query->released()->whereBetween('acquired_on', [$start->toDateString(), $end->toDateString()]))
            ->exists();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bounds(ResearchCommitment $commitment, CarbonInterface $anchor): array
    {
        $date = Carbon::parse($anchor);

        return match ($commitment->cadence()) {
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfQuarter(), $date->copy()->endOfQuarter()],
        };
    }

    private function previous(ResearchCommitment $commitment, CarbonInterface $anchor): CarbonInterface
    {
        $date = Carbon::parse($anchor);

        return match ($commitment->cadence()) {
            'week' => $date->copy()->subWeek(),
            'month' => $date->copy()->subMonth(),
            default => $date->copy()->subQuarter(),
        };
    }
}
