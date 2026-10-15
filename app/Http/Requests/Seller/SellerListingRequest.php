<?php

namespace App\Http\Requests\Seller;

use App\Enums\Amenity;
use App\Enums\AmenityState;
use App\Enums\BoundaryStatus;
use App\Enums\LandTenure;
use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\OccupationStatus;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipStructure;
use App\Enums\ParcelReferenceType;
use App\Enums\PriceBasis;
use App\Enums\PropertyType;
use App\Enums\RoadAccess;
use App\Enums\SubdivisionStatus;
use App\Models\Locality;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Draft-level validation for a landowner listing. Fields are typed but optional so
 * an incomplete listing can be saved; completeness is checked at submission.
 */
class SellerListingRequest extends FormRequest
{
    /**
     * Free-text declarations shared by sale and lease listings, stored in listing_terms.
     *
     * @var array<int, string>
     */
    public const SHARED_TERMS = [
        'current_use',
        'improvements',
        'terrain',
        'other_utilities',
        'legal_access',
        'encumbrances',
        'restrictions',
    ];

    /**
     * @var array<int, string>
     */
    public const SALE_TERMS = [
        'possession_timing',
        'transfer_restrictions',
        'seller_instructions',
    ];

    /**
     * @var array<int, string>
     */
    public const LEASE_TERMS = [
        'deposit_terms',
        'payment_frequency',
        'permitted_use',
        'renewal_terms',
        'rent_review',
        'improvements_permitted',
        'subletting_permitted',
        'use_restrictions',
        'termination_conditions',
        'leasing_instructions',
    ];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('workspace.seller') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sale = fn (array $rules): array => ['exclude_unless:purpose,'.ListingPurpose::Sale->value, ...$rules];
        $lease = fn (array $rules): array => ['exclude_unless:purpose,'.ListingPurpose::Lease->value, ...$rules];
        $text = ['nullable', 'string', 'max:1000'];

        $rules = [
            'purpose' => ['required', Rule::enum(ListingPurpose::class)],
            'relationship' => ['required', Rule::in(array_map(fn (OwnerRelationship $relationship) => $relationship->value, OwnerRelationship::sellerFacing()))],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'property_type' => ['nullable', Rule::enum(PropertyType::class)],
            'land_use' => ['nullable', Rule::enum(LandUse::class)],

            'county_id' => ['nullable', 'integer', Rule::exists('counties', 'id')->where('is_active', true), 'required_with:locality_id'],
            'locality_id' => ['nullable', 'integer', Rule::exists('localities', 'id')->where('is_active', true), 'required_with:county_id'],
            'landmark' => ['nullable', 'string', 'max:160'],
            'access_description' => $text,
            'road_access' => ['nullable', Rule::enum(RoadAccess::class)],
            'public_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:public_longitude'],
            'public_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:public_latitude'],
            'restricted_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:restricted_longitude'],
            'restricted_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:restricted_latitude'],

            'parcel_identifier' => ['nullable', 'string', 'max:80'],
            'parcel_reference_type' => ['nullable', Rule::enum(ParcelReferenceType::class)],
            'survey_reference' => ['nullable', 'string', 'max:80'],
            'acreage' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'offered_acreage' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'minimum_acreage' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'subdivision_status' => ['nullable', Rule::enum(SubdivisionStatus::class)],
            'boundary_status' => ['nullable', Rule::enum(BoundaryStatus::class)],

            'tenure' => ['nullable', Rule::enum(LandTenure::class)],
            'ownership_structure' => ['nullable', Rule::enum(OwnershipStructure::class)],
            'occupation_status' => ['nullable', Rule::enum(OccupationStatus::class)],
            'amenities' => ['nullable', 'array:'.implode(',', array_map(fn (Amenity $amenity) => $amenity->value, Amenity::cases()))],
            'amenities.*' => ['nullable', Rule::in([AmenityState::Available->value, AmenityState::Unavailable->value, AmenityState::Unknown->value])],
            'terms' => ['nullable', 'array:'.implode(',', self::SHARED_TERMS)],

            'sale' => $sale(['nullable', 'array']),
            'sale.price_basis' => $sale(['nullable', Rule::in(array_map(fn (PriceBasis $basis) => $basis->value, PriceBasis::forPurpose(ListingPurpose::Sale)))]),
            'sale.amount' => $sale(['nullable', 'integer', 'min:1', 'max:100000000000']),
            'sale.negotiable' => $sale(['nullable', 'boolean']),
            'sale.available_from' => $sale(['nullable', 'date']),

            'lease' => $lease(['nullable', 'array']),
            'lease.price_basis' => $lease(['nullable', Rule::in(array_map(fn (PriceBasis $basis) => $basis->value, PriceBasis::forPurpose(ListingPurpose::Lease)))]),
            'lease.amount' => $lease(['nullable', 'integer', 'min:1', 'max:100000000000']),
            'lease.negotiable' => $lease(['nullable', 'boolean']),
            'lease.available_from' => $lease(['nullable', 'date']),
            'lease.min_months' => $lease(['nullable', 'integer', 'min:1', 'max:1200']),
            'lease.max_months' => $lease(['nullable', 'integer', 'min:1', 'max:1200']),
        ];

        foreach (self::SHARED_TERMS as $key) {
            $rules['terms.'.$key] = $text;
        }

        foreach (self::SALE_TERMS as $key) {
            $rules['sale.'.$key] = $sale($text);
        }

        foreach (self::LEASE_TERMS as $key) {
            $rules['lease.'.$key] = $lease($text);
        }

        return $rules;
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $countyId = $this->input('county_id');
                $localityId = $this->input('locality_id');

                if ($countyId !== null && $localityId !== null
                    && (int) Locality::query()->whereKey($localityId)->value('county_id') !== (int) $countyId) {
                    $validator->errors()->add('locality_id', 'Choose a locality in the selected county.');
                }

                $acreage = $this->filled('acreage') ? (float) $this->input('acreage') : null;
                $offered = $this->filled('offered_acreage') ? (float) $this->input('offered_acreage') : null;
                $minimum = $this->filled('minimum_acreage') ? (float) $this->input('minimum_acreage') : null;

                if ($acreage !== null && $offered !== null && $offered > $acreage + 0.0001) {
                    $validator->errors()->add('offered_acreage', 'The acreage offered cannot exceed the registered parcel acreage.');
                }

                if ($minimum !== null && $minimum > ($offered ?? $acreage ?? $minimum) + 0.0001) {
                    $validator->errors()->add('minimum_acreage', 'The minimum portion cannot exceed the acreage offered.');
                }

                $minMonths = $this->input('lease.min_months');
                $maxMonths = $this->input('lease.max_months');

                if ($this->input('purpose') === ListingPurpose::Lease->value
                    && filled($minMonths) && filled($maxMonths) && (int) $maxMonths < (int) $minMonths) {
                    $validator->errors()->add('lease.max_months', 'The maximum lease duration cannot be shorter than the minimum.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'sale.amount' => 'asking price',
            'sale.price_basis' => 'price basis',
            'lease.amount' => 'asking rent',
            'lease.price_basis' => 'rent basis',
            'lease.min_months' => 'minimum lease duration',
            'lease.max_months' => 'maximum lease duration',
            'county_id' => 'county',
            'locality_id' => 'locality',
        ];
    }
}
