<?php

namespace App\Http\Requests;

use App\Enums\ListingPurpose;
use App\Enums\PropertyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FindLandRequest extends FormRequest
{
    protected $redirectRoute = 'find-land.index';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'integer', Rule::exists('counties', 'id')->where('is_active', true)],
            'locality' => ['nullable', 'integer', Rule::exists('localities', 'id')->where('is_active', true)],
            'mode' => ['nullable', Rule::in(['area', 'nearby'])],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:1', 'max:'.config('lamu.find_land_max_radius_km', 100)],
            'purpose' => ['nullable', Rule::enum(ListingPurpose::class)],
            'type' => ['nullable', Rule::enum(PropertyType::class)],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'budget_basis' => ['nullable', Rule::in(['total', 'per_acre'])],
            'min_acres' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'max_acres' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'sort' => ['nullable', Rule::in(['nearest', 'newest', 'price_asc', 'price_desc', 'acres_asc', 'acres_desc'])],
            'view' => ['nullable', Rule::in(['cards', 'list'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['price', 'acres'] as $range) {
                if (is_numeric($this->input('min_'.$range)) && is_numeric($this->input('max_'.$range))
                    && (float) $this->input('min_'.$range) > (float) $this->input('max_'.$range)) {
                    $validator->errors()->add('max_'.$range, 'The maximum must be at least the minimum.');
                }
            }
            if (($this->filled('min_price') || $this->filled('max_price') || in_array($this->input('sort'), ['price_asc', 'price_desc'], true)) && ! $this->filled('purpose')) {
                $validator->errors()->add('purpose', 'Choose sale or lease to compare budgets on the same basis.');
            }
        }];
    }
}
