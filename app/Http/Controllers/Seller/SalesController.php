<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Http\Controllers\Controller;
use App\Models\OfferPresentation;
use App\Models\Property;
use App\Models\PropertyTransaction;
use App\Models\SiteVisit;
use App\Services\Seller\SellerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function enquiries(Request $request): View
    {
        $properties = $this->properties($request);
        $briefings = \App\Models\SellerEnquiryBriefing::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->with('property')
            ->latest()
            ->paginate(12);

        return view('seller.sales.enquiries', ['briefings' => $briefings]);
    }

    public function viewings(Request $request): View
    {
        $visits = SiteVisit::query()
            ->whereIn('property_id', $this->properties($request)->pluck('id'))
            ->with('property')
            ->latest()
            ->paginate(12);

        return view('seller.sales.viewings', ['visits' => $visits]);
    }

    public function instructViewing(Request $request, SiteVisit $siteVisit, SellerPortal $portal): RedirectResponse
    {
        abort_unless($portal->visibleToSeller($request->user(), $siteVisit->property), 403);

        $data = $request->validate(['body' => ['required', 'string', 'min:5', 'max:2000']]);
        $portal->instruct($siteVisit->property, $request->user(), \App\Enums\SaleInstructionKind::Viewing, $data['body']);

        return back()->with('status', 'Viewing instruction recorded.');
    }

    public function offers(Request $request): View
    {
        $presentations = OfferPresentation::query()
            ->whereIn('property_id', $this->properties($request)->pluck('id'))
            ->with('property')
            ->latest()
            ->paginate(12);

        return view('seller.sales.offers', ['presentations' => $presentations]);
    }

    public function respond(Request $request, OfferPresentation $offerPresentation, SellerPortal $portal): RedirectResponse
    {
        abort_unless($portal->visibleToSeller($request->user(), $offerPresentation->property), 403);

        $data = $request->validate(['seller_response' => ['required', 'in:accept,decline']]);
        $portal->respondToOffer($offerPresentation, $request->user(), $data['seller_response']);

        return back()->with('status', 'Offer response recorded. The property remains unsold until staff complete a sale.');
    }

    public function transactions(Request $request): View
    {
        $transactions = PropertyTransaction::query()
            ->whereIn('property_id', $this->properties($request)->pluck('id'))
            ->with('property')
            ->latest()
            ->paginate(12);

        return view('seller.sales.transactions', ['transactions' => $transactions]);
    }

    public function statement(Request $request, PropertyTransaction $propertyTransaction, SellerPortal $portal): View
    {
        abort_unless($portal->visibleToSeller($request->user(), $propertyTransaction->property), 403);

        return view('seller.sales.statement', ['transaction' => $propertyTransaction->load('property')]);
    }

    private function properties(Request $request)
    {
        abort_unless($request->user()->hasPermission('workspace.seller'), 403);

        return Property::query()
            ->whereHas('owners', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', OwnerLinkStatus::Verified)
                ->whereIn('relationship', OwnerRelationship::sellerFacing()))
            ->get();
    }
}
