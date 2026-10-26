<?php

namespace App\Http\Requests\Staff;

use App\Enums\LandTenure;
use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\MarketingRole;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipStructure;
use App\Enums\ParcelReferenceType;
use App\Enums\PriceBasis;
use App\Enums\PropertyType;
use App\Enums\SaleMode;
use App\Enums\SubdivisionStatus;
use App\Http\Requests\Seller\SellerListingRequest;
use App\Models\Property;
use App\Models\User;
use App\Services\Media\PropertyMediaLibrary;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StaffPropertyListingRequest extends SellerListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sale = fn (array $rules): array => ['exclude_unless:purpose,'.ListingPurpose::Sale->value, ...$rules];
        $lease = fn (array $rules): array => ['exclude_unless:purpose,'.ListingPurpose::Lease->value, ...$rules];
        $partial = fn (array $rules): array => ['exclude_unless:sale_mode,'.SaleMode::Partial->value, ...$rules];

        $rules = parent::rules();
        unset($rules['relationship']);

        $rules['summary'] = ['required', 'string', 'max:500'];
        $rules['description'] = ['required', 'string', 'max:5000'];
        $rules['property_type'] = ['required', Rule::enum(PropertyType::class)];
        $rules['land_use'] = ['required', Rule::enum(LandUse::class)];
        $rules['county_id'] = ['required', 'integer', Rule::exists('counties', 'id')->where('is_active', true)];
        $rules['locality_id'] = ['required', 'integer', Rule::exists('localities', 'id')->where('is_active', true)];
        $rules['public_latitude'] = ['required', 'numeric', 'between:-90,90'];
        $rules['public_longitude'] = ['required', 'numeric', 'between:-180,180'];
        $rules['restricted_latitude'] = ['required', 'numeric', 'between:-90,90'];
        $rules['restricted_longitude'] = ['required', 'numeric', 'between:-180,180'];
        $rules['parcel_identifier'] = ['required', 'string', 'max:80'];
        $rules['parcel_reference_type'] = ['required', Rule::enum(ParcelReferenceType::class)];
        $rules['acreage'] = ['required', 'numeric', 'gt:0', 'max:100000'];
        $rules['tenure'] = ['required', Rule::enum(LandTenure::class)];
        $rules['ownership_structure'] = ['required', Rule::enum(OwnershipStructure::class)];
        $rules['sale.price_basis'] = $sale(['required', Rule::in(array_map(fn (PriceBasis $basis) => $basis->value, PriceBasis::forPurpose(ListingPurpose::Sale)))]);
        $rules['sale.amount'] = $sale(['required', 'integer', 'min:1', 'max:100000000000']);
        $rules['lease.price_basis'] = $lease(['required', Rule::in(array_map(fn (PriceBasis $basis) => $basis->value, PriceBasis::forPurpose(ListingPurpose::Lease)))]);
        $rules['lease.amount'] = $lease(['required', 'integer', 'min:1', 'max:100000000000']);
        $rules['lease.min_months'] = $lease(['required', 'integer', 'min:1', 'max:1200']);
        $rules['minimum_acreage'] = $partial(['required', 'numeric', 'gt:0', 'max:100000']);
        $rules['subdivision_status'] = $partial(['required', Rule::enum(SubdivisionStatus::class)]);

        return array_merge($rules, [
            'relationship' => ['nullable', Rule::in(array_map(fn (OwnerRelationship $relationship) => $relationship->value, OwnerRelationship::sellerFacing()))],
            'reference' => ['nullable', 'string', 'max:32', Rule::unique('properties', 'reference')],
            'marketing_role' => ['nullable', 'required_with:owner_user_id', Rule::enum(MarketingRole::class)],
            'seller_name' => ['nullable', 'string', 'max:160'],
            'seller_contact' => ['nullable', 'string', 'max:80'],
            'owner_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('id', User::permission('workspace.seller')->select('id'))),
            ],
            'owner_relationship' => ['nullable', 'required_with:owner_user_id', Rule::enum(OwnerRelationship::class)],
            'photos' => ['required', 'array', 'min:1', 'max:12'],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.PropertyMediaLibrary::MAX_KILOBYTES],
        ]);
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return array_merge(parent::after(), [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('purpose') === ListingPurpose::Sale->value
                    && $this->input('sale_mode') === SaleMode::Partial->value
                    && blank($this->input('offered_acreage'))) {
                    $validator->errors()->add('offered_acreage', 'Add the acreage offered for partial sale.');
                }
            },
        ]);
    }
}
