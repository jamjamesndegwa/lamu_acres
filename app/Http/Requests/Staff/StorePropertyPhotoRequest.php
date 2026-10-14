<?php

namespace App\Http\Requests\Staff;

use App\Models\Property;
use App\Services\Media\PropertyMediaLibrary;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property && ($this->user()?->can('manageMedia', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.PropertyMediaLibrary::MAX_KILOBYTES],
        ];
    }
}
