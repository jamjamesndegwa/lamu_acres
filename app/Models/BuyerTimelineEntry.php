<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'buyer_id',
    'actor_id',
    'property_id',
    'sourcing_request_id',
    'property_match_id',
    'summary',
    'occurred_at',
])]
class BuyerTimelineEntry extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @param  array{property_id?: int|null, sourcing_request_id?: int|null, property_match_id?: int|null}  $links
     */
    public static function record(User $buyer, string $summary, array $links = [], ?User $actor = null): self
    {
        return static::query()->create([
            'buyer_id' => $buyer->id,
            'actor_id' => $actor?->id ?? auth()->id(),
            'property_id' => $links['property_id'] ?? null,
            'sourcing_request_id' => $links['sourcing_request_id'] ?? null,
            'property_match_id' => $links['property_match_id'] ?? null,
            'summary' => $summary,
            'occurred_at' => now(),
        ]);
    }
}
