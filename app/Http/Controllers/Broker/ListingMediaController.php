<?php

namespace App\Http\Controllers\Broker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePropertyPhotoRequest;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Services\Media\PropertyMediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function reorder(Request $request, Property $property, PropertyMediaLibrary $library): RedirectResponse
    {
        abort_unless($property->submitted_by === $request->user()->id, 404);

        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*' => ['integer', 'min:1', 'max:1000'],
        ]);

        $library->reorder($property, $data['positions']);

        return back()->with('status', 'Photo order saved.');
    }
}
