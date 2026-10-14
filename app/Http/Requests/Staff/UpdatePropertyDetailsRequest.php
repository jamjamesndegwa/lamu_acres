<?php

namespace App\Http\Requests\Staff;

use App\Enums\Amenity;
use App\Enums\AmenityState;
use App\Enums\DocumentCheckState;
use App\Enums\RoadAccess;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property && ($this->user()?->can('update', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $amenityRules = [];

        foreach (Amenity::cases() as $amenity) {
            $amenityRules['amenities.'.$amenity->value] = ['required', Rule::enum(AmenityState::class)];
        }

        return [
            'road_access' => ['required', Rule::enum(RoadAccess::class)],
            'infrastructure_note' => ['nullable', 'string', 'max:500'],
            'highlights' => ['nullable', 'string', 'max:2000'],
            'documents.title_search' => ['required', Rule::enum(DocumentCheckState::class)],
            'documents.survey_plan' => ['required', Rule::enum(DocumentCheckState::class)],
            ...$amenityRules,
        ];
    }
}
