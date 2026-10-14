<?php

namespace App\Models;

use App\Enums\LocalityKind;
use Database\Factories\LocalityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'county_id',
    'parent_id',
    'name',
    'slug',
    'kind',
    'latitude',
    'longitude',
    'boundary_wkt',
    'is_active',
])]
class Locality extends Model
{
    /** @use HasFactory<LocalityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => LocalityKind::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(LocalityAlias::class);
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
