<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Amenity;
use App\Enums\DocumentCheckState;
use App\Enums\RoadAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePropertyPhotoRequest;
use App\Http\Requests\Staff\StorePropertyVideoRequest;
use App\Http\Requests\Staff\UpdatePropertyDetailsRequest;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Services\Media\PropertyMediaLibrary;
use App\Services\Media\PropertyPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyMediaController extends Controller
{
    public function edit(Property $property): View
    {
        $this->authorize('update', $property);
        $property->load(['media' => fn ($query) => $query->orderBy('position'), 'highlights', 'amenityStates', 'documentChecks', 'priceChanges', 'availabilityEvents']);

        return view('staff.properties.media', [
            'property' => $property,
            'photos' => $this->photos($property),
            'amenities' => Amenity::cases(),
            'roads' => RoadAccess::cases(),
            'documentStates' => DocumentCheckState::cases(),
        ]);
    }

    public function storePhoto(StorePropertyPhotoRequest $request, Property $property, PropertyMediaLibrary $library): RedirectResponse
    {
        $library->storePhoto($request->user(), $property, $request->file('photo'));

        return back()->with('status', 'Photo stored. The public copy has had location data removed.');
    }

    public function storeVideo(StorePropertyVideoRequest $request, Property $property, PropertyMediaLibrary $library): RedirectResponse
    {
        $library->storeVideo($request->user(), $property, $request->string('video_url')->toString());

        return back()->with('status', 'Video link saved.');
    }

    public function destroy(Request $request, Property $property, PropertyMedia $propertyMedia, PropertyMediaLibrary $library): RedirectResponse
    {
        abort_unless($propertyMedia->property_id === $property->id, 404);
        $this->authorize('delete', $propertyMedia);
        $library->delete($propertyMedia);

        return back()->with('status', 'Media removed.');
    }

    public function cover(Property $property, PropertyMedia $propertyMedia, PropertyMediaLibrary $library): RedirectResponse
    {
        $this->authorize('update', $property);
        abort_unless($propertyMedia->property_id === $property->id, 404);
        $library->makeCover($property, $propertyMedia);

        return back()->with('status', 'Cover photo updated.');
    }

    public function reorder(Request $request, Property $property, PropertyMediaLibrary $library): RedirectResponse
    {
        $this->authorize('update', $property);
        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*' => ['integer', 'min:1', 'max:1000'],
        ]);
        $library->reorder($property, $data['positions']);

        return back()->with('status', 'Photo order saved.');
    }

    public function original(Property $property, PropertyMedia $propertyMedia): StreamedResponse
    {
        abort_unless($propertyMedia->property_id === $property->id, 404);
        $this->authorize('viewOriginal', $propertyMedia);
        abort_unless($this->privatePath($property, $propertyMedia), 404);

        $name = basename(str_replace('\\', '/', (string) $propertyMedia->original_name));

        return Storage::disk($propertyMedia->disk ?: 'local')->download($propertyMedia->path, $name === '' ? 'file' : $name);
    }

    public function preview(Property $property, PropertyMedia $propertyMedia): StreamedResponse
    {
        $this->authorize('update', $property);
        abort_unless($propertyMedia->property_id === $property->id, 404);
        abort_unless(PropertyMediaLibrary::isPublicDerivative($property, $propertyMedia), 404);

        return Storage::disk('local')->response($propertyMedia->public_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function updateDetails(UpdatePropertyDetailsRequest $request, Property $property, PropertyPresentation $presentation): RedirectResponse
    {
        $presentation->update($property, $request->validated());

        return back()->with('status', 'Property details saved.');
    }

    /**
     * @return array<int, array{id: int, url: string, cover: bool, position: int, name: string}>
     */
    private function photos(Property $property): array
    {
        return $property->media
            ->where('kind', 'photo')
            ->filter(fn (PropertyMedia $media): bool => PropertyMediaLibrary::isPublicDerivative($property, $media))
            ->sortBy('position')
            ->map(fn (PropertyMedia $media): array => [
                'id' => $media->id,
                'url' => route('staff.properties.media.preview', [$property, $media]),
                'cover' => (bool) $media->is_cover,
                'position' => (int) $media->position,
                'name' => $media->original_name,
            ])
            ->values()
            ->all();
    }

    private function privatePath(Property $property, PropertyMedia $media): bool
    {
        $path = (string) $media->path;

        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        return str_starts_with($path, 'property-originals/'.$property->id.'/')
            || str_starts_with($path, 'broker-listings/'.$property->id.'/');
    }
}
