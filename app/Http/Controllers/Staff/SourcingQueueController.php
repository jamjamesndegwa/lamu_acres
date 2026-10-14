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

        $status = SourcingRequestStatus::tryFrom((string) $request->query('status', ''));
        $assigned = (string) $request->query('assigned', '');
        $queue = (string) $request->route('queue', 'requirements');

        $requests = SourcingRequest::query()
            ->visibleToStaff($request->user())
            ->with(['buyer', 'assignee'])
            ->withCount([
                'matches',
                'matches as proposed_count' => fn ($query) => $query->whereNull('dismissed_at'),
            ])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($assigned === 'unassigned', fn ($query) => $query->whereNull('assigned_to'))
            ->when($assigned === 'mine', fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($queue === 'matching', fn ($query) => $query->has('matches'))
            ->when($queue === 'approvals', fn ($query) => $query->whereHas(
                'matches',
                fn ($query) => $query->whereNull('dismissed_at'),
            ))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('staff.sourcing.index', [
            'heading' => match ($queue) {
                'matching' => 'Matching',
                'approvals' => 'Approvals',
                default => 'Requirements',
            },
            'requests' => $requests,
            'statuses' => SourcingRequestStatus::cases(),
            'assignees' => $request->user()->hasPermission('staff.sourcing')
                ? User::permission('workspace.staff')->orderBy('name')->get()
                : collect(),
        ]);
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
            'mapEmbed' => $this->radiusMap($sourcingRequest),
            'exactMatches' => $sourcingRequest->matches->where('band', MatchBand::Exact)->whereNull('dismissed_at'),
            'nearMatches' => $sourcingRequest->matches->where('band', MatchBand::Near)->whereNull('dismissed_at'),
            'declinedMatches' => $sourcingRequest->matches->whereNotNull('dismissed_at'),
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

    public function reject(Request $request, SourcingRequest $sourcingRequest, SourcingMatch $sourcingMatch, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('assign', $sourcingRequest);
        abort_unless((int) $sourcingMatch->sourcing_request_id === (int) $sourcingRequest->id, 404);

        $workflow->declineCandidate($sourcingMatch, $request->user());

        return back()->with('status', 'Match kept off the buyer shortlist.');
    }

    private function radiusMap(SourcingRequest $sourcingRequest): ?string
    {
        if ($sourcingRequest->centre_latitude === null || $sourcingRequest->centre_longitude === null) {
            return null;
        }

        $lat = round((float) $sourcingRequest->centre_latitude, 4);
        $lng = round((float) $sourcingRequest->centre_longitude, 4);
        $span = max(0.02, ((float) ($sourcingRequest->radius_km ?: 5)) / 111);
        $bbox = implode(',', [$lng - $span, $lat - $span, $lng + $span, $lat + $span]);

        return 'https://www.openstreetmap.org/export/embed.html?bbox='.$bbox.'&layer=mapnik&marker='.$lat.','.$lng;
    }
}
