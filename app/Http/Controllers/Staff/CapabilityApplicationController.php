<?php

namespace App\Http\Controllers\Staff;

use App\Enums\CapabilityApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\CapabilityApplication;
use App\Services\Access\CapabilityReviewer;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CapabilityApplicationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CapabilityApplication::class);

        return view('staff.applications.index', [
            'applications' => CapabilityApplication::query()
                ->with('user:id,name,email')
                ->latest('updated_at')
                ->paginate(20),
        ]);
    }

    public function edit(Request $request, CapabilityApplication $application): View
    {
        $this->authorize('decide', $application);

        return view('staff.applications.decide', [
            'application' => $application->load('user:id,name,email'),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function update(Request $request, CapabilityApplication $application, CapabilityReviewer $reviewer): RedirectResponse
    {
        $this->authorize('decide', $application);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                CapabilityApplicationStatus::Approved->value,
                CapabilityApplicationStatus::Rejected->value,
                CapabilityApplicationStatus::InformationRequired->value,
                CapabilityApplicationStatus::Suspended->value,
            ])],
            'decision_reason' => ['required', 'string', 'max:500'],
        ]);

        $reviewer->decide(
            $request->user(),
            $application,
            CapabilityApplicationStatus::from($data['status']),
            $data['decision_reason'],
        );

        return redirect()
            ->route('staff.applications.index')
            ->with('status', 'Application decision recorded.');
    }
}
