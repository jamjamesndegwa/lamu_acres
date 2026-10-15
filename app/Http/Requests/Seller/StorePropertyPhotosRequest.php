<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Staff\StorePropertyPhotoRequest;
use App\Services\Media\PropertyMediaLibrary;

class StorePropertyPhotosRequest extends StorePropertyPhotoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => ['required_without:photos', 'prohibits:photos', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.PropertyMediaLibrary::MAX_KILOBYTES],
            'photos' => ['required_without:photo', 'prohibits:photo', 'array', 'min:1', 'max:12'],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.PropertyMediaLibrary::MAX_KILOBYTES],
        ];
    }
}
