<?php

namespace App\Models;

use App\Enums\MapLayerKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'county_id',
    'name',
    'slug',
    'kind',
    'source',
    'description',
    'latitude',
    'longitude',
    'boundary_wkt',
    'is_visible',
])]
class MapLayer extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => MapLayerKind::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_visible' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function hasBoundary(): bool
    {
        return filled($this->boundary_wkt);
    }

    public function hasPoint(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
