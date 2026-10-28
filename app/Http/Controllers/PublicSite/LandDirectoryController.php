<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\SavedProperty;
use App\Services\Catalog\AppliedLandFilters;
use App\Services\Catalog\PropertyCatalog;
use App\Services\Catalog\PublicListing;
use App\Services\Verification\VerificationDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandDirectoryController extends Controller
{
    public function index(
        Request $request,
        PropertyCatalog $catalog,
        PublicListing $publicListing,
        AppliedLandFilters $appliedFilters,
        ?string $county = null,
        ?string $locality = null,
    ): View {
        $countyModel = $this->county($county);
        $localityModel = $this->locality($countyModel, $locality);
        $filters = $catalog->filters($request);
        $listings = $catalog->listings($filters, $countyModel, $localityModel);
        $listings->getCollection()->load(['media' => fn ($query) => $query
            ->where('kind', 'photo')
            ->whereNotNull('public_path')
            ->orderByDesc('is_cover')
            ->orderBy('position')]);

        return view('public.land.index', [
            'listings' => $listings->through(fn (Property $property): array => $publicListing->card($property)),
            'mapMarkers' => $catalog->mapMarkers($filters, $countyModel, $localityModel),
            'filterChips' => $appliedFilters->chips($request, $filters, $countyModel, $localityModel),
            'clearFiltersUrl' => $appliedFilters->clearUrl($request),
            'filters' => $filters,
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

        return $this->listing($property, app(PublicListing::class));
    }

    public function showItem(Property $property): View|RedirectResponse
    {
        abort_unless($property->isPubliclyListed(), 404);

        $property->load(['areaCounty:id,name,slug', 'areaLocality:id,name,slug,county_id']);

        if ($property->areaCounty !== null && $property->areaLocality !== null) {
            return redirect()->route('land.show', [$property->areaCounty, $property->areaLocality, $property]);
        }

        return $this->listing($property, app(PublicListing::class));
    }

    private function listing(Property $property, PublicListing $publicListing): View
    {
        $saved = false;
        $user = auth()->user();

        if ($user !== null) {
            $saved = SavedProperty::query()
                ->where('user_id', $user->id)
                ->where('property_id', $property->id)
                ->exists();
        }

        $listing = $publicListing->present($property);

        foreach ([
            'seller_name',
            'seller_contact',
            'parcel_identifier',
            'restricted_latitude',
            'restricted_longitude',
            'public_latitude',
            'public_longitude',
        ] as $field) {
            $property->offsetUnset($field);
        }

        $property->unsetRelation('media');

        return view('public.land.show', [
            'listing' => $listing,
            'property' => $property,
            'saved' => $saved,
            'passport' => app(VerificationDesk::class)->publicSummary($property->load('verificationItems')),
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
