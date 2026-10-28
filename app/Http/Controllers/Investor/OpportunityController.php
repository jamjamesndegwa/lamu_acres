<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\SavedProperty;
use App\Services\Catalog\AppliedLandFilters;
use App\Services\Catalog\PropertyCatalog;
use App\Services\Catalog\PublicListing;
use App\Services\Intelligence\LocalityIntelligenceSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityController extends Controller
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

        $directoryUrl = route('investor.opportunities.index', array_filter([
            'county' => $countyModel?->slug,
            'locality' => $localityModel?->slug,
        ]));

        return view('investor.opportunities.index', [
            'listings' => $listings->through(function (Property $property) use ($publicListing): array {
                $card = $publicListing->card($property);
                $card['url'] = route('investor.opportunities.show', $property);
                $card['image'] = str_starts_with($card['image'], 'images/')
                    ? asset($card['image'])
                    : $card['image'];

                return $card;
            }),
            'filterChips' => $appliedFilters->chips($request, $filters, $countyModel, $localityModel, 'investor.opportunities.index'),
            'clearFiltersUrl' => $appliedFilters->clearUrl($request, $countyModel, $localityModel, 'investor.opportunities.index'),
            'filters' => $filters,
            'counties' => $catalog->counties(),
            'county' => $countyModel,
            'locality' => $localityModel,
            'directoryUrl' => $directoryUrl,
            'indexRoute' => 'investor.opportunities.index',
        ]);
    }

    public function show(
        Property $property,
        PublicListing $publicListing,
        LocalityIntelligenceSummary $intelligence,
    ): View {
        abort_unless($property->isPubliclyListed(), 404);

        $property->load(['areaCounty:id,name,slug', 'areaLocality:id,name,slug,county_id']);

        $saved = SavedProperty::query()
            ->where('user_id', auth()->id())
            ->where('property_id', $property->id)
            ->exists();

        $listing = $publicListing->present($property);

        foreach ([
            'seller_name',
            'seller_contact',
            'parcel_identifier',
            'restricted_latitude',
            'restricted_longitude',
        ] as $field) {
            $property->offsetUnset($field);
        }

        $property->unsetRelation('media');

        return view('investor.opportunities.show', [
            'property' => $property,
            'listing' => $listing,
            'saved' => $saved,
            'intelligence' => $intelligence->compose($property),
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
