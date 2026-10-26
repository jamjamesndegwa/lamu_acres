<?php

namespace App\Http\Controllers\Land;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreEnquiryRequest;
use App\Http\Requests\Buyer\StorePurchaseRequest;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\SavedProperty;
use App\Services\Buyer\EnquiryRecorder;
use App\Services\Buyer\SavedPropertyService;
use App\Services\Sales\SalesPipeline;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(): View
    {
        return view('land.index', [
            'properties' => Property::query()->published()->orderByDesc('published_at')->paginate(12),
        ]);
    }

    public function show(Property $property): View
    {
        abort_unless($property->isPublished(), 404);

        $saved = false;

        if ($user = auth()->user()) {
            $saved = SavedProperty::query()
                ->where('user_id', $user->id)
                ->where('property_id', $property->id)
                ->exists();
        }

        return view('land.show', [
            'property' => $property,
            'saved' => $saved,
        ]);
    }

    public function createEnquiry(Property $property): View|RedirectResponse
    {
        abort_unless($property->isPublished(), 404);
        $this->authorize('enquire', $property);

        if (! Turbo::wantsModal(request()) && ! session()->has('errors')) {
            return redirect()->to($property->publicUrl());
        }

        return view('land.enquire', ['property' => $property]);
    }

    public function storeEnquiry(StoreEnquiryRequest $request, Property $property, EnquiryRecorder $recorder): RedirectResponse
    {
        $recorder->submit($request->user(), $property, $request->string('message')->toString(), $request);

        return redirect()
            ->route('buyer.enquiries.index')
            ->with('status', 'Your enquiry has been sent.');
    }

    public function createPurchaseRequest(Property $property): View|RedirectResponse
    {
        abort_unless($property->isPubliclyListed() && ! $property->isLease(), 404);
        $this->authorize('enquire', $property);

        if (! Turbo::wantsModal(request())) {
            return redirect()->to($property->publicUrl());
        }

        return view('land.purchase-request', ['property' => $property]);
    }

    public function storePurchaseRequest(
        StorePurchaseRequest $request,
        Property $property,
        EnquiryRecorder $recorder,
        SalesPipeline $pipeline,
    ): RedirectResponse {
        $existingRequest = Enquiry::query()
            ->where('buyer_id', $request->user()->id)
            ->where('property_id', $property->id)
            ->where('purchase_request', true)
            ->whereHas('salesTransaction', fn ($query) => $query->open())
            ->latest()
            ->first();

        if ($existingRequest !== null) {
            return redirect()
                ->route('buyer.enquiries.show', $existingRequest)
                ->with('status', 'You already have an active purchase request for this property.');
        }

        $enquiry = $recorder->submitPurchaseRequest(
            $request->user(),
            $property,
            (int) $request->validated('requested_price'),
            $request->filled('requested_acreage') ? (float) $request->validated('requested_acreage') : null,
            $request->validated('message'),
            $request,
            $pipeline,
        );

        $reference = $enquiry->salesTransaction?->reference;

        return redirect()
            ->route('buyer.enquiries.show', $enquiry)
            ->with('status', $reference
                ? 'Your purchase request '.$reference.' has been sent to the Lamu Acres sales desk.'
                : 'Your purchase request has been sent to the Lamu Acres sales desk.');
    }

    public function save(Request $request, Property $property, SavedPropertyService $saved): RedirectResponse
    {
        abort_unless($property->isPublished(), 404);
        $this->authorize('save', $property);

        $saved->save($request->user(), $property);

        return back()->with('status', 'Property saved.');
    }

    public function unsave(Request $request, Property $property, SavedPropertyService $saved): RedirectResponse
    {
        $this->authorize('save', $property);

        $saved->remove($request->user(), $property);

        return back()->with('status', 'Property removed from saved land.');
    }
}
