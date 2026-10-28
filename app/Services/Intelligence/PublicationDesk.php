<?php

namespace App\Services\Intelligence;

use App\Enums\MessageEvent;
use App\Enums\PublicationStatus;
use App\Models\Entitlement;
use App\Models\IntelligenceAudit;
use App\Models\ResearchReport;
use App\Models\User;
use App\Services\Communications\MessageDesk;
use App\Services\Research\CommitmentDesk;
use App\Services\Research\WatchAlertContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PublicationDesk
{
    public function reference(string $prefix, string $model): string
    {
        do {
            $reference = $prefix.'-'.strtoupper(Str::random(8));
        } while ($model::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function slug(string $title, string $model): string
    {
        do {
            $slug = Str::slug($title).'-'.Str::lower(Str::random(5));
        } while ($model::query()->where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function audit(Model $subject, User $actor, string $action, ?PublicationStatus $from, ?PublicationStatus $to, ?string $note = null, array $changes = []): void
    {
        IntelligenceAudit::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'actor_id' => $actor->id,
            'action' => $action,
            'from_publication' => $from?->value,
            'to_publication' => $to?->value,
            'note' => $note,
            'changes' => $changes === [] ? null : $changes,
        ]);
    }

    public function submit(Model $subject, User $actor): void
    {
        $from = $subject->publication_status;
        abort_unless(in_array($from, [PublicationStatus::Draft, PublicationStatus::Withdrawn], true), 422);

        $subject->update(['publication_status' => PublicationStatus::Review]);
        $this->audit($subject, $actor, 'submitted', $from, PublicationStatus::Review);
    }

    public function publish(Model $subject, User $actor): void
    {
        $from = $subject->publication_status;
        abort_unless($from === PublicationStatus::Review, 422);

        $subject->update([
            'publication_status' => PublicationStatus::Published,
            'published_at' => now(),
            'reviewed_by' => $actor->id,
        ]);
        $this->audit($subject, $actor, 'published', $from, PublicationStatus::Published);

        if ($subject instanceof ResearchReport) {
            $this->announceReport($subject);
        }

        app(CommitmentDesk::class)->afterPublication($subject->refresh());
    }

    private function announceReport(ResearchReport $report): void
    {
        $report->loadMissing(['localities', 'projects']);
        $watcherIds = app(WatchAlertContext::class)->watcherUserIdsForReport($report);

        User::query()->whereHas('entitlements', function ($query): void {
            $query->where('code', Entitlement::INVESTOR_PASS)
                ->where('status', Entitlement::STATUS_ACTIVE)
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
                });
        })->each(function (User $user) use ($report, $watcherIds): void {
            if (! $user->hasActiveInvestorPass()) {
                return;
            }

            if (in_array($user->id, $watcherIds, true)) {
                return;
            }

            app(MessageDesk::class)->send($user, MessageEvent::ReportPublished, [
                'name' => $user->name,
                'title' => $report->title,
                'reference' => $report->reference,
                'summary' => $report->summary ?: ($report->title.' is now available.'),
                'url' => route('investor.reports.show', $report),
            ], 'report:'.$report->id);
        });
    }

    public function revise(Model $subject, User $actor, array $changes): void
    {
        $status = $subject->publication_status;
        $this->audit($subject, $actor, 'revised', $status, $status, null, $changes);
    }

    public function withdraw(Model $subject, User $actor): void
    {
        $from = $subject->publication_status;
        abort_unless($from === PublicationStatus::Published, 422);

        $subject->update([
            'publication_status' => PublicationStatus::Withdrawn,
            'published_at' => null,
        ]);
        $this->audit(
            $subject,
            $actor,
            'withdrawn',
            $from,
            PublicationStatus::Withdrawn,
            'Withdrawn from public preview and premium subscriber access.',
        );
    }
}
