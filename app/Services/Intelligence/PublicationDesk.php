<?php

namespace App\Services\Intelligence;

use App\Enums\PublicationStatus;
use App\Models\IntelligenceAudit;
use App\Models\User;
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
        app(\App\Services\Research\CommitmentDesk::class)->afterPublication($subject->refresh());
    }

    public function revise(Model $subject, User $actor, array $changes): void
    {
        $status = $subject->publication_status;
        $this->audit($subject, $actor, 'revised', $status, $status, null, $changes);
    }
}
