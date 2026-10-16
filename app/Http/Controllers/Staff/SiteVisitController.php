<?php

namespace App\Http\Controllers\Staff;

use App\Enums\SiteVisitStatus;
use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteVisitController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SiteVisit::class);
        abort_unless(
            $request->user()->hasPermission('staff.buyers')
            || SiteVisit::query()->where('assigned_to', $request->user()->id)->exists(),
            403,
        );

        $calendar = $request->query('view') === 'calendar';
        $query = SiteVisit::query()
            ->visibleToStaff($request->user())
            ->when($request->filled('record'), fn ($query) => $query->whereKey((int) $request->query('record')))
            ->with(['buyer', 'property', 'assignee']);

        $visits = $calendar
            ? $query->orderByRaw('coalesce(confirmed_for, requested_for) asc')->limit(80)->get()
            : $query->latest()->paginate(20)->withQueryString();

        return view('staff.visits.index', [
            'visits' => $visits,
            'calendar' => $calendar,
            'agents' => User::permission('workspace.staff')->orderBy('name')->get(),
        ]);
    }

    public function assign(Request $request, SiteVisit $siteVisit, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('assign', $siteVisit);

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);
        $agent = User::query()->findOrFail($data['assigned_to']);
        abort_unless($agent->hasPermission('workspace.staff'), 422);

        $workflow->assignVisit($siteVisit, $agent, $request->user());

        return back()->with('status', 'Visit agent assigned.');
    }

    public function update(Request $request, SiteVisit $siteVisit, InterestWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(SiteVisitStatus::class)],
            'confirmed_for' => ['required_if:status,confirmed', 'nullable', 'date'],
        ]);
        $status = SiteVisitStatus::from($data['status']);

        if ($status === SiteVisitStatus::Cancelled) {
            $this->authorize('cancel', $siteVisit);
        } else {
            $this->authorize('manage', $siteVisit);
        }

        $confirmedFor = isset($data['confirmed_for']) ? Carbon::parse($data['confirmed_for']) : null;

        $workflow->transitionVisit($siteVisit, $status, $request->user(), $confirmedFor);

        return back()->with('status', 'Visit updated.');
    }
}
