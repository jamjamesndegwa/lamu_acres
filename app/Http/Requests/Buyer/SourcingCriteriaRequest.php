<?php

namespace App\Http\Requests\Buyer;

use App\Enums\Amenity;
use App\Enums\LandUse;
use App\Models\SourcingRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SourcingCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sourcingRequest = $this->route('sourcingRequest');

        if ($sourcingRequest instanceof SourcingRequest) {
            return $this->user()?->can('update', $sourcingRequest) ?? false;
        }

        return $this->user()?->can('create', SourcingRequest::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('polygon') || ! $this->filled('polygon_text')) {
            return;
        }

        $points = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $this->input('polygon_text')) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s*,\s*|\s+/', $line) ?: [];

            if (count($parts) >= 2) {
                $points[] = ['lat' => $parts[0], 'lng' => $parts[1]];
            }
        }

        $this->merge(['polygon' => $points]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_label' => ['nullable', 'string', 'max:160', 'required_without:centre_latitude'],
            'centre_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:centre_longitude', 'required_without:location_label'],
            'centre_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:centre_latitude'],
            'radius_km' => ['nullable', 'numeric', 'min:0.5', 'max:150', 'required_with:centre_latitude'],
            'polygon' => ['nullable', 'array', 'min:3'],
            'polygon.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'polygon.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'min_acreage' => ['nullable', 'numeric', 'min:0'],
            'max_acreage' => ['nullable', 'numeric', Rule::when($this->filled('min_acreage'), ['gte:min_acreage'])],
            'maximum_budget' => ['nullable', 'integer', 'min:1'],
            'intended_use' => ['nullable', Rule::enum(LandUse::class)],
            'mandatory' => ['nullable', 'array'],
            'mandatory.*' => ['string', Rule::enum(Amenity::class)],
            'preferred' => ['nullable', 'array'],
            'preferred.*' => ['string', Rule::enum(Amenity::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mandatory = array_map('strval', $this->input('mandatory', []));
            $preferred = array_map('strval', $this->input('preferred', []));

            if (array_intersect($mandatory, $preferred) !== []) {
                $validator->errors()->add('preferred', 'A preferred amenity cannot also be mandatory.');
            }
        });
    }
}
