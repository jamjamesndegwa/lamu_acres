<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\FindLandRequest;
use App\Models\County;
use App\Models\Locality;
use App\Models\PlanningRecord;
use App\Models\Property;
use App\Models\ResearchReport;
use App\Services\Catalog\LandFinder;
use App\Services\Catalog\PropertyCatalog;
use App\Services\Catalog\PublicListing;
use App\Services\Verification\VerificationDesk;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class FindLandController extends Controller
{
    public function __invoke(FindLandRequest $request, PropertyCatalog $catalog, LandFinder $finder, PublicListing $presenter, VerificationDesk $verification): Response
    {
        $input = $request->validated();
        $county = isset($input['county']) ? County::query()->active()->findOrFail($input['county']) : null;
        $locality = isset($input['locality']) ? Locality::query()->active()->whereHas('county', fn ($query) => $query->active())->with('county')->findOrFail($input['locality']) : null;
        if ($county !== null && $locality !== null && (int) $locality->county_id !== (int) $county->id) {
            throw ValidationException::withMessages(['locality' => 'Choose a locality within the selected county, or clear the county.']);
        }
        $county ??= $locality?->county;
        $filters = $catalog->filters($request);
        $search = $finder->search($filters, $input, $county, $locality);
        $listings = $search['query']->with(['passport', 'verificationItems', 'media' => fn ($query) => $query->where('kind', 'photo')->whereNotNull('public_path')->orderByDesc('is_cover')->orderBy('position')])
            ->paginate(12)->appends($input);
        $markers = [];
        $listings->through(function (Property $property) use ($presenter, $verification, &$markers): array {
            $card = $presenter->card($property);
            $card['type'] = $property->property_type?->label();
            $card['verification'] = $verification->publicSummary($property)['level'];
            $card['price_basis_label'] = $property->isLease() ? 'Rent for the whole offered area' : 'Total asking price for the offered area';
            $card['currency'] = $property->price_currency;
            $card['quoted_rate'] = $property->priceBasis()->isPerAcre() && $property->price_rate !== null
                ? $property->price_currency.' '.number_format($property->price_rate).' '.$property->priceBasis()->suffix() : null;
            $card['distance_km'] = $property->getAttribute('distance_km') !== null ? (float) $property->getAttribute('distance_km') : null;
            $card['save_url'] = auth()->user()?->can('save', $property) ? route('land.saved.store', $property) : null;
            if ($property->public_latitude !== null && $property->public_longitude !== null
                && abs((float) $property->public_latitude) <= 90 && abs((float) $property->public_longitude) <= 180) {
                $markers[] = ['latitude' => round((float) $property->public_latitude, 2), 'longitude' => round((float) $property->public_longitude, 2), 'title' => $card['title'], 'url' => $card['url']];
            }

            return $card;
        });
        $plans = $county ? PlanningRecord::query()->released()->where('county_id', $county->id)
            ->when($locality, fn ($query) => $query->where('locality_id', $locality->id))
            ->latest('published_at')->limit(3)->get(['id', 'slug', 'title', 'instrument_status']) : collect();
        $reports = $county ? ResearchReport::query()->released()->whereHas('localities', fn ($query) => $locality ? $query->whereKey($locality->id) : $query->where('county_id', $county->id))
            ->latest('published_at')->limit(3)->get(['id', 'slug', 'title']) : collect();

        return response()->view('public.find-land', [
            'filters' => $filters, 'input' => $input, 'listings' => $listings, 'counties' => $catalog->counties(),
            'county' => $county, 'locality' => $locality, 'centre' => $search['centre'], 'radius' => $search['radius'],
            'markers' => $markers, 'plans' => $plans, 'reports' => $reports,
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'origin');
    }
}
