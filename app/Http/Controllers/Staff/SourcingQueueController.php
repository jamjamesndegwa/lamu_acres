<?php

namespace App\Http\Controllers\Staff;

use App\Enums\MatchBand;
use App\Enums\SourcingRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AssignSourcingRequest;
use App\Http\Requests\Staff\UpdateSourcingStatusRequest;
use App\Models\SourcingMatch;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Services\Buyer\InterestWorkflow;
use App\Services\Staff\SourcingDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SourcingQueueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SourcingRequest::class);
        abort_unless(
            $request->user()->hasPermission('staff.sourcing')
            || SourcingRequest::query()->where('assigned_to', $request->user()->id)->exists(),
            403,
        );

        $requests = SourcingRequest::query()
            ->visibleToStaff($request->user())
            ->with(['buyer', 'assignee'])
            ->withCount('matches')
            ->latest()
            ->paginate(20);

        return view('staff.sourcing.index', ['requests' => $requests]);
    }

    public function show(Request $request, SourcingRequest $sourcingRequest): View
    {
        $this->authorize('view', $sourcingRequest);

        $sourcingRequest->load([
            'buyer',
            'assignee',
            'amenities',
            'activities.actor',
            'matches.property',
        ]);

        return view('staff.sourcing.show', [
            'sourcingRequest' => $sourcingRequest,
            'exactMatches' => $sourcingRequest->matches->where('band', MatchBand::Exact),
            'nearMatches' => $sourcingRequest->matches->where('band', MatchBand::Near),
            'assignees' => User::permission('workspace.staff')->orderBy('name')->get(),
            'statuses' => SourcingRequestStatus::cases(),
        ]);
    }

    public function assign(AssignSourcingRequest $request, SourcingRequest $sourcingRequest, SourcingDesk $desk): RedirectResponse
    {
        $desk->assign($sourcingRequest, $request->assignee(), $request->user());

        return back()->with('status', 'Sourcing request assigned.');
    }

    public function update(UpdateSourcingStatusRequest $request, SourcingRequest $sourcingRequest, SourcingDesk $desk): RedirectResponse
    {
        $desk->updateStatus($sourcingRequest, $request->enum('status', SourcingRequestStatus::class), $request->user());

        return back()->with('status', 'Sourcing status updated.');
    }

    public function approve(Request $request, SourcingRequest $sourcingRequest, SourcingMatch $sourcingMatch, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('assign', $sourcingRequest);
        abort_unless((int) $sourcingMatch->sourcing_request_id === (int) $sourcingRequest->id, 404);

        $workflow->approve($sourcingMatch, $request->user());

        return back()->with('status', 'Match added to the buyer shortlist.');
    }
}
