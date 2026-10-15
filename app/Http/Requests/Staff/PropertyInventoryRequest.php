<?php

namespace App\Http\Requests\Staff;

use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\MarketingRole;
use App\Enums\PriceBasis;
use App\Enums\PropertyType;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PropertyInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:32', Rule::unique('properties', 'reference')->ignore($this->route('property'))],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'summary' => ['nullable', 'string', 'max:500'],
            'county_id' => ['required', 'integer', Rule::exists('counties', 'id')->where('is_active', true)],
            'locality_id' => ['required', 'integer', Rule::exists('localities', 'id')->where('is_active', true)],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'land_use' => ['required', Rule::enum(LandUse::class)],
            'purpose' => ['required', Rule::enum(ListingPurpose::class)],
            'acreage' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'offered_acreage' => ['nullable', 'numeric', 'gt:0', 'lte:acreage'],
            'price_basis' => ['nullable', Rule::enum(PriceBasis::class)],
            'price_rate' => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn () => PriceBasis::tryFrom((string) $this->input('price_basis'))?->isPerAcre() ?? false)],
            'price_amount' => ['nullable', 'integer', 'min:0', 'required_without:price_rate'],
            'public_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:public_longitude'],
            'public_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:public_latitude'],
            'restricted_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:restricted_longitude'],
            'restricted_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:restricted_latitude'],
            'marketing_role' => ['required', Rule::enum(MarketingRole::class)],
            'represented_by' => ['nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', 'exists:properties,id'],
            'portion_label' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $basis = PriceBasis::tryFrom((string) $this->input('price_basis'));
                $purpose = ListingPurpose::tryFrom((string) $this->input('purpose'));

                if ($basis !== null && $basis !== PriceBasis::Total && $purpose !== null && $basis->purpose() !== $purpose) {
                    $validator->errors()->add('price_basis', 'Choose a price basis that matches the listing purpose.');
                }
            },
        ];
    }
}
