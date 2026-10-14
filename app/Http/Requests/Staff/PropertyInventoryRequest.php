<?php

namespace App\Http\Requests\Staff;

use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\MarketingRole;
use App\Enums\PropertyType;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'price_amount' => ['required', 'integer', 'min:0'],
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
}
