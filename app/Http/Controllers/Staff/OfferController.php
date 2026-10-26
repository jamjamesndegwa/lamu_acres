<?php

namespace App\Http\Controllers\Staff;

use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateOfferStatusRequest;
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
        abort_unless(
            $request->user()->hasPermission('staff.buyers')
            || $request->user()->hasPermission('staff.sales')
            || $request->user()->hasPermission('staff.properties'),
            403,
        );

        $offers = Offer::query()
            ->visibleToStaff($request->user())
            ->with(['buyer', 'property', 'presentations'])
            ->when($request->filled('record'), fn ($query) => $query->whereKey((int) $request->query('record')))
            ->latest()
            ->paginate(20);

        return view('staff.offers.index', [
            'offers' => $offers,
            'statuses' => array_filter(
                OfferStatus::cases(),
                fn (OfferStatus $status): bool => $status !== OfferStatus::Withdrawn,
            ),
        ]);
    }

    public function show(Request $request, Offer $offer): View
    {
        $this->authorize('view', $offer);

        $offer->load([
            'buyer.buyerProfile',
            'property.areaCounty:id,name,slug',
            'property.areaLocality:id,name,slug',
            'presentations',
            'match',
        ]);

        return view('staff.offers.show', [
            'offer' => $offer,
            'property' => $offer->property,
            'buyer' => $offer->buyer,
        ]);
    }

    public function update(UpdateOfferStatusRequest $request, Offer $offer, InterestWorkflow $workflow): RedirectResponse
    {
        $workflow->transitionOffer($offer, $request->enum('status', OfferStatus::class), $request->user());

        return redirect()
            ->route('staff.offers.show', $offer)
            ->with('status', 'Offer status updated.');
    }
}
