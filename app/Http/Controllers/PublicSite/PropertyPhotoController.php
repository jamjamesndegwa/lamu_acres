<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Services\Media\PropertyMediaLibrary;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyPhotoController extends Controller
{
    public function show(string $county, string $locality, Property $property, PropertyMedia $propertyMedia): StreamedResponse
    {
        $property->loadMissing(['areaCounty:id,slug', 'areaLocality:id,slug,county_id']);
        abort_unless($property->isPubliclyListed(), 404);
        abort_unless($property->areaCounty?->slug === $county, 404);
        abort_unless($property->areaLocality?->slug === $locality, 404);
        abort_unless($propertyMedia->property_id === $property->id, 404);
        abort_unless(PropertyMediaLibrary::isPublicDerivative($property, $propertyMedia), 404);

        return Storage::disk('local')->response($propertyMedia->public_path, null, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
