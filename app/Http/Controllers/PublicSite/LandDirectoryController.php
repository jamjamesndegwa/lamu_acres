<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\SavedProperty;
use App\Services\Catalog\PropertyCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandDirectoryController extends Controller
{
    public function index(Request $request, PropertyCatalog $catalog, ?string $county = null, ?string $locality = null): View
    {
        $countyModel = $this->county($county);
        $localityModel = $this->locality($countyModel, $locality);

        return view('public.land.index', [
            'listings' => $catalog->listings($catalog->filters($request), $countyModel, $localityModel),
            'filters' => $catalog->filters($request),
            'counties' => $catalog->counties(),
            'county' => $countyModel,
            'locality' => $localityModel,
        ]);
    }

    public function show(string $county, string $locality, Property $property): View
    {
        $property->load(['areaCounty:id,name,slug', 'areaLocality:id,name,slug,county_id']);

        abort_unless($property->isPubliclyListed(), 404);
        abort_unless($property->areaCounty?->slug === $county, 404);
        abort_unless($property->areaLocality?->slug === $locality, 404);

        return $this->listing($property);
    }

    public function showItem(Property $property): View|RedirectResponse
    {
        abort_unless($property->isPubliclyListed(), 404);

        $property->load(['areaCounty:id,name,slug', 'areaLocality:id,name,slug,county_id']);

        if ($property->areaCounty !== null && $property->areaLocality !== null) {
            return redirect()->route('land.show', [$property->areaCounty, $property->areaLocality, $property]);
        }

        return $this->listing($property);
    }

    private function listing(Property $property): View
    {
        $saved = false;
        $user = auth()->user();

        if ($user !== null) {
            $saved = SavedProperty::query()
                ->where('user_id', $user->id)
                ->where('property_id', $property->id)
                ->exists();
        }

        return view('public.land.show', [
            'listing' => $property,
            'saved' => $saved,
        ]);
    }

    private function county(?string $slug): ?County
    {
        if ($slug === null) {
            return null;
        }

        return County::query()->where('slug', $slug)->firstOrFail();
    }

    private function locality(?County $county, ?string $slug): ?Locality
    {
        if ($slug === null) {
            return null;
        }

        abort_if($county === null, 404);

        return $county->localities()->where('slug', $slug)->firstOrFail();
    }
}
