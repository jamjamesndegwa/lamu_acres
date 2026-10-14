<?php

namespace App\Models\Concerns;

use App\Enums\PublicationStatus;
use App\Models\IntelligenceAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPublication
{
    public function isReleased(): bool
    {
        return $this->publication_status === PublicationStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query
            ->where('publication_status', PublicationStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(IntelligenceAudit::class, 'subject');
    }

    /**
     * @return array<string, string>
     */
    protected function publicationCasts(): array
    {
        return [
            'publication_status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
