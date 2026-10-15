<?php

namespace App\Models;

use App\Enums\BoundaryStatus;
use App\Enums\LandTenure;
use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\MarketingRole;
use App\Enums\OccupationStatus;
use App\Enums\OwnershipStructure;
use App\Enums\ParcelReferenceType;
use App\Enums\PriceBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoadAccess;
use App\Enums\SubdivisionStatus;
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
    'price_basis',
    'price_rate',
    'price_negotiable',
    'offered_acreage',
    'minimum_acreage',
    'tenure',
    'ownership_structure',
    'occupation_status',
    'available_from',
    'landmark',
    'access_description',
    'parcel_reference_type',
    'survey_reference',
    'subdivision_status',
    'boundary_status',
    'lease_min_months',
    'lease_max_months',
    'listing_terms',
    'submitted_at',
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
            'price_basis' => PriceBasis::class,
            'price_rate' => 'integer',
            'price_negotiable' => 'boolean',
            'offered_acreage' => 'decimal:4',
            'minimum_acreage' => 'decimal:4',
            'tenure' => LandTenure::class,
            'ownership_structure' => OwnershipStructure::class,
            'occupation_status' => OccupationStatus::class,
            'available_from' => 'date',
            'parcel_reference_type' => ParcelReferenceType::class,
            'subdivision_status' => SubdivisionStatus::class,
            'boundary_status' => BoundaryStatus::class,
            'lease_min_months' => 'integer',
            'lease_max_months' => 'integer',
            'listing_terms' => 'array',
            'submitted_at' => 'datetime',
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

    public function isLease(): bool
    {
        return $this->purpose === ListingPurpose::Lease;
    }

    public function priceBasis(): PriceBasis
    {
        return $this->price_basis ?? PriceBasis::Total;
    }

    /**
     * Acreage offered to the market; null means the whole parcel still held.
     */
    public function offeredAcreage(): float
    {
        if ($this->offered_acreage !== null) {
            return (float) $this->offered_acreage;
        }

        return $this->remainingAcreage();
    }

    public function isPartialOffer(): bool
    {
        return $this->offered_acreage !== null
            && (float) $this->offered_acreage + 0.0001 < (float) $this->acreage;
    }

    /**
     * Total asking price (sale) or rent for the offered area per period (lease).
     */
    public static function computePriceAmount(PriceBasis $basis, ?int $rate, ?float $acreage): ?int
    {
        if ($rate === null) {
            return null;
        }

        if (! $basis->isPerAcre()) {
            return $rate;
        }

        if ($acreage === null || $acreage <= 0) {
            return null;
        }

        return (int) round($rate * $acreage);
    }

    public function formattedPrice(): ?string
    {
        if ($this->price_amount === null) {
            return null;
        }

        $basis = $this->priceBasis();
        $amount = 'KSh '.number_format((int) $this->price_amount);

        if ($basis->isPerAcre() && $this->price_rate !== null) {
            $rate = 'KSh '.number_format((int) $this->price_rate).' '.$basis->suffix();
            $total = $basis === PriceBasis::PerAcre
                ? 'indicative total '.$amount
                : trim($amount.' '.$basis->periodSuffix()).' for '.rtrim(rtrim(number_format($this->offeredAcreage(), 2), '0'), '.').' acres';

            return $rate.' ('.$total.')';
        }

        return trim($amount.' '.$basis->periodSuffix());
    }

    public function pricePerAcre(): ?int
    {
        if ($this->isLease()) {
            return null;
        }

        if ($this->priceBasis() === PriceBasis::PerAcre && $this->price_rate !== null) {
            return (int) $this->price_rate;
        }

        $acreage = (float) ($this->offered_acreage ?? $this->acreage);

        if ($this->price_amount === null || $acreage <= 0) {
            return null;
        }

        return (int) round(((int) $this->price_amount) / $acreage);
    }

    public function listingTerm(string $key): mixed
    {
        return ($this->listing_terms ?? [])[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function listingTermLabels(): array
    {
        return [
            'current_use' => 'Current land use',
            'improvements' => 'Existing development or improvements',
            'terrain' => 'Terrain and topography',
            'other_utilities' => 'Other utilities',
            'legal_access' => 'Legal access status',
            'encumbrances' => 'Known encumbrances or disputes',
            'restrictions' => 'Environmental or planning restrictions',
            'possession_timing' => 'Expected possession',
            'transfer_restrictions' => 'Restrictions affecting transfer',
            'seller_instructions' => 'Seller instructions',
            'deposit_terms' => 'Deposit or security terms',
            'payment_frequency' => 'Rent payment frequency',
            'permitted_use' => 'Intended or permitted use',
            'renewal_terms' => 'Renewal terms',
            'rent_review' => 'Rent review',
            'improvements_permitted' => 'Improvements by the tenant',
            'subletting_permitted' => 'Subletting',
            'use_restrictions' => 'Use or occupation restrictions',
            'termination_conditions' => 'Termination and restoration',
            'leasing_instructions' => 'Leasing instructions',
        ];
    }

    /**
     * Seller declarations that may be shown on the public listing once approved.
     * Encumbrances, transfer restrictions and instructions to Lamu Acres stay internal.
     *
     * @return array<string, string>
     */
    public function publicListingTerms(): array
    {
        $public = [
            'current_use',
            'improvements',
            'terrain',
            'other_utilities',
            'restrictions',
            'possession_timing',
            'payment_frequency',
            'permitted_use',
            'renewal_terms',
            'rent_review',
            'improvements_permitted',
            'subletting_permitted',
            'use_restrictions',
            'deposit_terms',
            'termination_conditions',
        ];
        $labels = self::listingTermLabels();

        return collect($public)
            ->filter(fn (string $key) => filled($this->listingTerm($key)))
            ->mapWithKeys(fn (string $key) => [$labels[$key] => (string) $this->listingTerm($key)])
            ->all();
    }

    /**
     * Status changes allowed from the current status for this listing purpose.
     *
     * @return array<int, PropertyStatus>
     */
    public function allowedTransitions(): array
    {
        $excluded = $this->isLease() ? PropertyStatus::Sold : PropertyStatus::Leased;

        return array_values(array_filter(
            $this->status->transitions(),
            fn (PropertyStatus $status) => $status !== $excluded,
        ));
    }

    public function hasOpenInformationRequest(): bool
    {
        return $this->informationRequests()->whereNull('responded_at')->exists();
    }

    /**
     * A submitter may change listing details and photos only before staff
     * publish it: while drafting, or while answering a staff request in review.
     */
    public function isEditableBySubmitter(): bool
    {
        return $this->status === PropertyStatus::Draft
            || ($this->status === PropertyStatus::Review && $this->hasOpenInformationRequest());
    }

    /**
     * Seller submissions carry an owner link for the submitter; broker submissions do not.
     */
    public function submittedBySeller(): bool
    {
        return $this->submitted_by !== null
            && $this->owners()->where('user_id', $this->submitted_by)->exists();
    }

    /**
     * Where the submitting broker or landowner follows this listing in their workspace.
     */
    public function submitterUrl(): string
    {
        return $this->submittedBySeller()
            ? route('seller.properties.show', $this)
            : route('broker.listings.show', $this);
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
