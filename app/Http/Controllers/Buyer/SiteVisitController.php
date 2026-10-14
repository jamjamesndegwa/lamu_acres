<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\SiteVisitStatus;
use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteVisitController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SiteVisit::class);

        $visits = SiteVisit::query()
            ->where('buyer_id', $request->user()->id)
            ->with(['property', 'assignee:id,name'])
            ->latest()
            ->paginate(12);

        return view('buyer.visits.index', ['visits' => $visits]);
    }

    public function cancel(Request $request, SiteVisit $siteVisit, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('cancel', $siteVisit);
        $workflow->transitionVisit($siteVisit, SiteVisitStatus::Cancelled, $request->user());

        return back()->with('status', 'Site visit cancelled.');
    }

    public function acknowledge(Request $request, SiteVisit $siteVisit, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('acknowledge', $siteVisit);
        $workflow->acknowledge($siteVisit, $request->user());

        return back()->with('status', 'Viewing acknowledged.');
    }
}
