<?php

namespace App\Models;

use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\MarketingRole;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoadAccess;
use App\Enums\VerificationLevel;
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
    'description',
    'purpose',
    'property_type',
    'land_use',
    'availability',
    'featured',
    'status',
    'published_at',
    'public_latitude',
    'public_longitude',
    'marketing_role',
    'represented_by',
    'submitted_by',
    'parcel_identifier',
    'seller_name',
    'seller_contact',
    'parent_id',
    'portion_label',
    'amenities',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected $hidden = [
        'restricted_latitude',
        'restricted_longitude',
        'seller_name',
        'seller_contact',
    ];

    protected function casts(): array
    {
        return [
            'acreage' => 'decimal:4',
            'price_amount' => 'integer',
            'purpose' => ListingPurpose::class,
            'property_type' => PropertyType::class,
            'land_use' => LandUse::class,
            'marketing_role' => MarketingRole::class,
            'public_latitude' => 'decimal:7',
            'public_longitude' => 'decimal:7',
            'restricted_latitude' => 'decimal:7',
            'restricted_longitude' => 'decimal:7',
            'availability' => PropertyAvailability::class,
            'featured' => 'boolean',
            'amenities' => 'array',
            'status' => PropertyStatus::class,
            'published_at' => 'datetime',
            'road_access' => RoadAccess::class,
            'verification_level' => VerificationLevel::class,
            'last_reviewed_at' => 'datetime',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function portions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'represented_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PropertyMedia::class);
    }

    public function amenityStates(): HasMany
    {
        return $this->hasMany(PropertyAmenity::class);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(PropertyHighlight::class)->orderBy('position');
    }

    public function priceChanges(): HasMany
    {
        return $this->hasMany(PropertyPriceChange::class)->orderBy('id');
    }

    public function availabilityEvents(): HasMany
    {
        return $this->hasMany(PropertyAvailabilityEvent::class)->orderBy('id');
    }

    public function documentChecks(): HasMany
    {
        return $this->hasMany(PropertyDocumentCheck::class);
    }

    public function informationRequests(): HasMany
    {
        return $this->hasMany(PropertyInformationRequest::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PropertyAudit::class);
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

    public function soldPortionAcreage(): float
    {
        if (array_key_exists('sold_portion_acreage', $this->attributes)) {
            return (float) $this->attributes['sold_portion_acreage'];
        }

        return (float) $this->portions()->where('status', PropertyStatus::Sold)->sum('acreage');
    }

    public function remainingAcreage(): float
    {
        if ($this->parent_id !== null) {
            return (float) $this->acreage;
        }

        return max(0, (float) $this->acreage - $this->soldPortionAcreage());
    }

    public function savedBy(): HasMany
    {
        return $this->hasMany(SavedProperty::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(PropertyOwner::class);
    }

    public function ownershipDocuments(): HasMany
    {
        return $this->hasMany(OwnershipDocument::class);
    }

    public function authorities(): HasMany
    {
        return $this->hasMany(MarketingAuthority::class);
    }

    public function saleInstructions(): HasMany
    {
        return $this->hasMany(SaleInstruction::class)->latest();
    }

    public function briefings(): HasMany
    {
        return $this->hasMany(SellerEnquiryBriefing::class)->latest();
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(OfferPresentation::class)->latest();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PropertyTransaction::class)->latest();
    }

    public function passport(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VerificationPassport::class);
    }

    public function verificationItems(): HasMany
    {
        return $this->hasMany(VerificationItem::class);
    }

    public function evidenceFiles(): HasMany
    {
        return $this->hasMany(EvidenceFile::class);
    }

    public function verificationReviews(): HasMany
    {
        return $this->hasMany(VerificationReviewLog::class)->latest();
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class)->latest();
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
