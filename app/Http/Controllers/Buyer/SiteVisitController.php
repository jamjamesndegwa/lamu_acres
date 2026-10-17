<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\SiteVisitStatus;
use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function confirmDetails(Request $request, SiteVisit $siteVisit, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('confirmDetails', $siteVisit);
        $workflow->confirmVisitDetails($siteVisit, $request->user());

        return back()->with('status', 'Appointment details confirmed.');
    }

    public function requestReschedule(Request $request, SiteVisit $siteVisit, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('requestReschedule', $siteVisit);
        $data = $request->validate([
            'preferred_for' => ['required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $workflow->requestVisitReschedule(
            $siteVisit,
            $request->user(),
            Carbon::parse($data['preferred_for']),
            $data['note'] ?? null,
        );

        return back()->with('status', 'Your reschedule request was sent to the visit desk. Your current appointment remains confirmed until staff responds.');
    }
}
