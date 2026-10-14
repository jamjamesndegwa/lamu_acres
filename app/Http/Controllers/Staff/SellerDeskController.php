<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AuthorityStatus;
use App\Enums\MessageEvent;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\MarketingAuthority;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyTransaction;
use App\Models\User;
use App\Services\Communications\MessageDesk;
use App\Services\Seller\SellerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SellerDeskController extends Controller
{
    public function verifyOwner(Request $request, PropertyOwner $propertyOwner, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.verification') || $request->user()->hasPermission('staff.properties'), 403);

        $data = $request->validate(['share_percent' => ['nullable', 'numeric', 'min:0', 'max:100']]);
        $portal->verifyLink($propertyOwner, $request->user(), isset($data['share_percent']) ? (float) $data['share_percent'] : null);

        return back()->with('status', 'Ownership link verified.');
    }

    public function addOwner(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'relationship' => ['required', Rule::enum(OwnerRelationship::class)],
        ]);
        $seller = User::query()->findOrFail($data['user_id']);
        abort_unless($seller->hasPermission('workspace.seller'), 422);

        $portal->addOwner($property, $seller, OwnerRelationship::from($data['relationship']), $request->user());

        return back()->with('status', 'Owner linked to the existing property.');
    }

    public function authorities(Request $request): View
    {
        abort_unless($request->user()->hasPermission('staff.brokerage'), 403);

        return view('staff.brokerage.authorities', [
            'authorities' => MarketingAuthority::query()->with(['property', 'seller'])->latest()->paginate(20),
        ]);
    }

    public function decideAuthority(Request $request, MarketingAuthority $marketingAuthority, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties'), 403);

        $data = $request->validate(['status' => ['required', Rule::in(['active', 'declined', 'withdrawn'])]]);
        $portal->decideAuthority($marketingAuthority, AuthorityStatus::from($data['status']), $request->user());

        return back()->with('status', 'Marketing authority updated.');
    }

    public function approveListing(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties'), 403);
        app(\App\Services\Verification\VerificationOperations::class)->assertClear($property);

        $property->status = PropertyStatus::Published;
        $property->published_at = now();
        $property->availability = PropertyAvailability::Available;
        $property->save();

        if ($property->submitter !== null) {
            app(MessageDesk::class)->send($property->submitter, MessageEvent::PropertyApproved, [
                'name' => $property->submitter->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => $property->title.' is approved for listing.',
                'url' => route('home'),
            ], 'property:'.$property->id);
        }

        return back()->with('status', 'Listing approved.');
    }

    public function applyAvailability(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties'), 403);

        $data = $request->validate(['availability' => ['required', Rule::in(['available', 'unavailable'])]]);
        $property->availability = PropertyAvailability::from($data['availability']);
        $property->save();

        return back()->with('status', 'Availability updated.');
    }

    public function briefing(Request $request, Enquiry $enquiry): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.buyers'), 403);

        $data = $request->validate(['body' => ['required', 'string', 'min:5', 'max:2000']]);
        $enquiry->property->briefings()->create([
            'enquiry_id' => $enquiry->id,
            'shared_by' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('status', 'Enquiry shared with the seller.');
    }

    public function presentOffer(Request $request, Offer $offer, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.sales') || $request->user()->hasPermission('staff.buyers'), 403);

        $data = $request->validate(['summary' => ['required', 'string', 'min:5', 'max:2000']]);
        $portal->presentOffer($offer, $request->user(), $data['summary']);

        return back()->with('status', 'Offer presented to the seller.');
    }

    public function transaction(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.sales'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TransactionStatus::class)],
            'amount' => ['nullable', 'integer', 'min:0'],
            'statement' => ['nullable', 'string', 'max:5000'],
        ]);

        PropertyTransaction::query()->create([
            'reference' => 'TX-'.strtoupper(Str::random(8)),
            'property_id' => $property->id,
            'status' => $data['status'],
            'amount' => $data['amount'] ?? null,
            'statement' => $data['statement'] ?? null,
        ]);

        return back()->with('status', 'Transaction statement recorded.');
    }
}
