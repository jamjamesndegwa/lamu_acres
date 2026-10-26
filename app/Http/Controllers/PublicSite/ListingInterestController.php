<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StorePublicOfferRequest;
use App\Models\ListingDocumentRequest;
use App\Models\Property;
use App\Models\PropertyVisitRequest;
use App\Services\Buyer\InterestWorkflow;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingInterestController extends Controller
{
    public function createOffer(Property $property): View|RedirectResponse
    {
        abort_unless($property->isPubliclyListed(), 404);

        if (! auth()->check()) {
            return redirect()->route('login', [
                'return_to' => route('land.offers.create', $property),
            ]);
        }

        $this->authorize('offer', $property);

        if (! Turbo::wantsModal(request()) && ! session()->has('errors')) {
            return redirect()->to($property->publicUrl().'#make-offer');
        }

        return view('public.land.offer', ['property' => $this->publicProperty($property)]);
    }

    public function storeOffer(StorePublicOfferRequest $request, Property $property, InterestWorkflow $workflow): RedirectResponse
    {
        abort_unless($property->isPubliclyListed(), 404);

        $workflow->submitListingOffer(
            $request->user(),
            $property,
            (int) $request->validated('amount'),
            $request->validated('message'),
        );

        $redirect = $request->user()->hasPermission('workspace.buyer')
            ? route('buyer.offers.index')
            : $property->publicUrl();

        return redirect()
            ->to($redirect)
            ->with('status', 'Your offer has been sent to the Lamu Acres desk.');
    }

    public function createVisit(Property $property): View|RedirectResponse
    {
        $this->guard($property);

        if (! Turbo::wantsModal(request())) {
            return redirect()->to($property->publicUrl());
        }

        return view('public.land.visit', ['property' => $this->publicProperty($property)]);
    }

    public function storeVisit(Request $request, Property $property, InterestWorkflow $workflow): RedirectResponse
    {
        $this->guard($property);
        $data = $request->validate([
            'preferred_on' => ['required', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $visitRequest = PropertyVisitRequest::query()->create([
            'property_id' => $property->id,
            'buyer_id' => $request->user()->id,
            'preferred_on' => $data['preferred_on'],
            'note' => $data['note'] ?? null,
        ]);
        $workflow->requestListingVisit($visitRequest);

        return redirect()
            ->to($property->publicUrl())
            ->with('status', 'Site visit request sent to the Lamu Acres desk.');
    }

    public function createDocuments(Property $property): View|RedirectResponse
    {
        $this->guardDocuments($property);

        if (! Turbo::wantsModal(request())) {
            return redirect()->to($property->publicUrl());
        }

        return view('public.land.documents', ['property' => $this->publicProperty($property)]);
    }

    public function storeDocuments(Request $request, Property $property): RedirectResponse
    {
        $this->guardDocuments($property);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        ListingDocumentRequest::query()->create([
            'property_id' => $property->id,
            'buyer_id' => $request->user()->id,
            'note' => $data['note'] ?? null,
        ]);

        return redirect()
            ->to($property->publicUrl())
            ->with('status', 'Document request sent. Original files are not included in this response.');
    }

    private function guard(Property $property): void
    {
        abort_unless($property->isPubliclyListed(), 404);
        $this->authorize('visit', $property);
    }

    private function guardDocuments(Property $property): void
    {
        abort_unless($property->isPubliclyListed(), 404);
        $this->authorize('requestDocuments', $property);
    }

    private function publicProperty(Property $property): Property
    {
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

        return $property;
    }
}
