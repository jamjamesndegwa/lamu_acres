<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\RejectMatchRequest;
use App\Http\Requests\Buyer\StoreDocumentAccessRequest;
use App\Http\Requests\Buyer\StoreOfferRequest;
use App\Http\Requests\Buyer\StoreSiteVisitRequest;
use App\Models\PropertyMatch;
use App\Models\SourcingMatch;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PropertyMatch::class);

        $matches = PropertyMatch::query()
            ->where('buyer_id', $request->user()->id)
            ->with(['property', 'sourcingRequest.matches'])
            ->latest('approved_at')
            ->paginate(12);

        return view('buyer.matches.index', ['matches' => $matches]);
    }

    public function show(Request $request, PropertyMatch $propertyMatch): View
    {
        $this->authorize('view', $propertyMatch);
        abort_unless($propertyMatch->isOwnedBy($request->user()), 403);

        $propertyMatch->load([
            'property',
            'sourcingRequest.amenities',
            'visits.assignee',
            'offers',
            'documentRequests',
            'timeline',
        ]);

        $fit = $propertyMatch->sourcing_request_id
            ? SourcingMatch::query()
                ->where('sourcing_request_id', $propertyMatch->sourcing_request_id)
                ->where('property_id', $propertyMatch->property_id)
                ->first()
            : null;

        $directions = null;

        if ($propertyMatch->directionsReleased()) {
            $directions = [
                'latitude' => $propertyMatch->property->restricted_latitude,
                'longitude' => $propertyMatch->property->restricted_longitude,
            ];
        }

        return view('buyer.matches.show', [
            'match' => $propertyMatch,
            'directions' => $directions,
            'fit' => $fit,
        ]);
    }

    public function reject(RejectMatchRequest $request, PropertyMatch $propertyMatch, InterestWorkflow $workflow): RedirectResponse
    {
        $workflow->reject($propertyMatch, $request->user(), $request->string('buyer_feedback')->toString());

        return redirect()
            ->route('buyer.matches.show', $propertyMatch)
            ->with('status', 'Match removed from your shortlist.');
    }

    public function visit(StoreSiteVisitRequest $request, PropertyMatch $propertyMatch, InterestWorkflow $workflow): RedirectResponse
    {
        $workflow->requestVisit(
            $propertyMatch,
            $request->user(),
            Carbon::parse($request->string('requested_for')->toString()),
            $request->string('notes')->toString() ?: null,
        );

        return back()->with('status', 'Site visit requested.');
    }

    public function offer(StoreOfferRequest $request, PropertyMatch $propertyMatch, InterestWorkflow $workflow): RedirectResponse
    {
        $workflow->submitOffer(
            $propertyMatch,
            $request->user(),
            $request->integer('amount'),
            $request->string('message')->toString() ?: null,
        );

        return back()->with('status', 'Offer submitted.');
    }

    public function documents(StoreDocumentAccessRequest $request, PropertyMatch $propertyMatch, InterestWorkflow $workflow): RedirectResponse
    {
        $workflow->requestDocuments(
            $propertyMatch,
            $request->user(),
            $request->string('note')->toString() ?: null,
        );

        return back()->with('status', 'Document access requested.');
    }
}
