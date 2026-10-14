<?php

namespace App\Models;

use App\Enums\ListingPurpose;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'slug',
    'title',
    'county',
    'locality',
    'county_id',
    'locality_id',
    'acreage',
    'price_amount',
    'price_currency',
    'summary',
    'purpose',
    'property_type',
    'availability',
    'featured',
    'status',
    'published_at',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'acreage' => 'decimal:4',
            'price_amount' => 'integer',
            'purpose' => ListingPurpose::class,
            'property_type' => PropertyType::class,
            'availability' => PropertyAvailability::class,
            'featured' => 'boolean',
            'status' => PropertyStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function areaCounty(): BelongsTo
    {
        return $this->belongsTo(County::class, 'county_id');
    }

    public function areaLocality(): BelongsTo
    {
        return $this->belongsTo(Locality::class, 'locality_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PropertyStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('availability', PropertyAvailability::Available);
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->published()->available();
    }

    public function isPublished(): bool
    {
        return $this->status === PropertyStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function isPubliclyListed(): bool
    {
        return $this->isPublished()
            && $this->availability === PropertyAvailability::Available;
    }

    public function formattedPrice(): ?string
    {
        if ($this->price_amount === null) {
            return null;
        }

        return 'KSh '.number_format((int) $this->price_amount);
    }

    public function pricePerAcre(): ?int
    {
        if ($this->price_amount === null || (float) $this->acreage <= 0) {
            return null;
        }

        return (int) round(((int) $this->price_amount) / (float) $this->acreage);
    }

    public function savedBy(): HasMany
    {
        return $this->hasMany(SavedProperty::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function locationLabel(): string
    {
        return collect([$this->locality, $this->county])->filter()->implode(', ');
    }

    public function publicUrl(): string
    {
        $this->loadMissing(['areaCounty:id,slug', 'areaLocality:id,slug']);

        if ($this->areaCounty !== null && $this->areaLocality !== null) {
            return route('land.show', [$this->areaCounty, $this->areaLocality, $this]);
        }

        return route('land.item', $this);
    }
}
