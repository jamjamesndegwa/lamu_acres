<?php

namespace App\Http\Controllers;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\PartnerCapability;
use App\Enums\Workspace;
use App\Models\CapabilityApplication;
use App\Models\Entitlement;
use App\Services\Access\CapabilityReviewer;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $applications = $user->capabilityApplications()->get()->keyBy(fn ($application) => $application->capability->value);
        $brokerApplication = $applications->get(PartnerCapability::Broker->value);

        return view('account.workspaces', [
            'workspaces' => $user->workspaces(),
            'applications' => $applications,
            'canApplyForPartnerCapability' => $user->can('create', CapabilityApplication::class),
            'brokerRenewalRequired' => $brokerApplication !== null
                && $brokerApplication->status === CapabilityApplicationStatus::Approved
                && ! app(CapabilityReviewer::class)->hasCurrentCapability($user, PartnerCapability::Broker),
            'investorPassStatus' => $this->investorPassStatus($request),
            'current' => $request->session()->get('workspace'),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function switch(Request $request, string $workspace): RedirectResponse
    {
        $target = Workspace::tryFrom($workspace);

        if ($target === null || ! $request->user()->canAccessWorkspace($target)) {
            abort(403);
        }

        $request->session()->put('workspace', $target->value);

        return redirect()->route($target->homeRoute());
    }

    private function investorPassStatus(Request $request): string
    {
        if ($request->user()->hasActiveInvestorPass()) {
            return 'Active';
        }

        $entitlement = $request->user()->entitlements()
            ->where('code', Entitlement::INVESTOR_PASS)
            ->latest('ends_at')
            ->first();

        return $entitlement !== null && ($entitlement->status === 'expired'
            || ($entitlement->ends_at !== null && $entitlement->ends_at->isPast()))
            ? 'Expired'
            : 'Inactive';
    }
}
