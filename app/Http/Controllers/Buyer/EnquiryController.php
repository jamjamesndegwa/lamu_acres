<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerTimelineEntry;
use App\Models\Enquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('workspace.buyer'), 403);

        $purchaseRequestsOnly = $request->query('type') === 'purchase';
        $enquiries = Enquiry::query()
            ->where('buyer_id', $request->user()->id)
            ->with([
                'property',
                'salesTransaction',
                'responses' => fn ($query) => $query->where('visible_to_buyer', true)->latest(),
            ])
            ->when($purchaseRequestsOnly, fn ($query) => $query->where('purchase_request', true))
            ->latest()
            ->paginate(12);

        return view('buyer.enquiries.index', [
            'enquiries' => $enquiries,
            'purchaseRequestsOnly' => $purchaseRequestsOnly,
        ]);
    }

    public function show(Request $request, Enquiry $enquiry): View
    {
        $this->authorize('view', $enquiry);
        abort_unless($enquiry->isOwnedBy($request->user()), 403);

        $enquiry->load([
            'property',
            'responses' => fn ($query) => $query->where('visible_to_buyer', true)->with('author'),
            'salesTransaction.agent:id,name',
            'salesTransaction.timeline' => fn ($query) => $query->where('visible_to_buyer', true)->with('actor:id,name')->latest(),
        ]);

        $activity = BuyerTimelineEntry::query()
            ->where('buyer_id', $request->user()->id)
            ->where('property_id', $enquiry->property_id)
            ->latest('occurred_at')
            ->get();

        return view('buyer.enquiries.show', [
            'enquiry' => $enquiry,
            'activity' => $activity,
        ]);
    }
}
