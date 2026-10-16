<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\Amenity;
use App\Enums\LandUse;
use App\Enums\MatchBand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\SourcingCriteriaRequest;
use App\Models\Locality;
use App\Models\SourcingRequest;
use App\Services\Buyer\SourcingRequestWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SourcingRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SourcingRequest::class);

        $requests = SourcingRequest::query()
            ->where('buyer_id', $request->user()->id)
            ->withCount([
                'matches as exact_matches_count' => fn ($query) => $query->where('band', MatchBand::Exact),
                'matches as near_matches_count' => fn ($query) => $query->where('band', MatchBand::Near),
            ])
            ->latest()
            ->paginate(12);

        return view('buyer.sourcing.index', ['requests' => $requests]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SourcingRequest::class);

        return view('buyer.sourcing.create', [
            ...$this->formData(),
            'profile' => $request->user()->buyerProfile,
        ]);
    }

    public function store(SourcingCriteriaRequest $request, SourcingRequestWriter $writer): RedirectResponse
    {
        $sourcingRequest = $writer->create($request->user(), $request->validated());

        return redirect()
            ->route('buyer.sourcing.show', $sourcingRequest)
            ->with('status', 'Your staff assistance request has been submitted.');
    }

    public function show(Request $request, SourcingRequest $sourcingRequest): View
    {
        $this->authorize('view', $sourcingRequest);
        abort_unless($sourcingRequest->isOwnedBy($request->user()), 403);

        $sourcingRequest->load([
            'amenities',
            'activities' => fn ($query) => $query->where('visible_to_buyer', true),
            'matches.property',
        ]);

        return view('buyer.sourcing.show', [
            'sourcingRequest' => $sourcingRequest,
            'exactMatches' => $sourcingRequest->matches->where('band', MatchBand::Exact),
            'nearMatches' => $sourcingRequest->matches->where('band', MatchBand::Near),
        ]);
    }

    public function edit(SourcingRequest $sourcingRequest): View
    {
        $this->authorize('update', $sourcingRequest);

        return view('buyer.sourcing.edit', [
            ...$this->formData(),
            'sourcingRequest' => $sourcingRequest->load('amenities'),
        ]);
    }

    public function update(SourcingCriteriaRequest $request, SourcingRequest $sourcingRequest, SourcingRequestWriter $writer): RedirectResponse
    {
        $writer->update($sourcingRequest, $request->user(), $request->validated());

        return redirect()
            ->route('buyer.sourcing.show', $sourcingRequest)
            ->with('status', 'Your search has been updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'landUses' => LandUse::cases(),
            'amenities' => Amenity::cases(),
            'places' => Locality::query()
                ->active()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->with('county:id,name')
                ->orderBy('name')
                ->get(['id', 'county_id', 'name', 'latitude', 'longitude']),
        ];
    }
}
