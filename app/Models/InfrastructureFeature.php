<?php

namespace App\Models;

use App\Enums\InfrastructureKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'county_id',
    'locality_id',
    'name',
    'slug',
    'kind',
    'source',
    'notes',
    'latitude',
    'longitude',
    'boundary_wkt',
    'is_active',
])]
class InfrastructureFeature extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => InfrastructureKind::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
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
