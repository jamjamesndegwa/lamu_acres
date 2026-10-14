<?php

namespace App\Http\Controllers\Broker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePropertyPhotoRequest;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Services\Media\PropertyMediaLibrary;
use Illuminate\Http\RedirectResponse;

class ListingMediaController extends Controller
{
    public function store(StorePropertyPhotoRequest $request, Property $property, PropertyMediaLibrary $library): RedirectResponse
    {
        $library->storePhoto($request->user(), $property, $request->file('photo'), 'broker-listings');

        return back()->with('status', 'Photo stored.');
    }

    public function destroy(Property $property, PropertyMedia $propertyMedia, PropertyMediaLibrary $library): RedirectResponse
    {
        abort_unless($propertyMedia->property_id === $property->id, 404);
        $this->authorize('delete', $propertyMedia);
        $library->delete($propertyMedia);

        return back()->with('status', 'Photo removed.');
    }
}
