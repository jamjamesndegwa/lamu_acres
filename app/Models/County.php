<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'code', 'latitude', 'longitude', 'boundary_wkt', 'is_active'])]
class County extends Model
{
    /** @use HasFactory<\Database\Factories\CountyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
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

    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
