<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Offer::class);

        $offers = Offer::query()
            ->where('buyer_id', $request->user()->id)
            ->with('property')
            ->when($request->filled('record'), fn ($query) => $query->whereKey((int) $request->query('record')))
            ->latest()
            ->paginate(12);

        return view('buyer.offers.index', ['offers' => $offers]);
    }

    public function withdraw(Request $request, Offer $offer, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('withdraw', $offer);
        $workflow->transitionOffer($offer, OfferStatus::Withdrawn, $request->user());

        return back()->with('status', 'Offer withdrawn.');
    }
}
