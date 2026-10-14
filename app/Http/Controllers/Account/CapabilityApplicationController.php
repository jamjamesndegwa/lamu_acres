<?php

namespace App\Http\Controllers\Account;

use App\Enums\PartnerCapability;
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
    public function index(Request $request): View
    {
        $this->authorize('create', CapabilityApplication::class);

        return view('account.applications.index', [
            'applications' => $request->user()->capabilityApplications()->orderBy('capability')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CapabilityApplication::class);

        $capability = PartnerCapability::tryFrom((string) $request->query('capability', ''));
        abort_if($capability === null, 404);

        return view('account.applications.form', [
            'capability' => $capability,
            'application' => $request->user()->capabilityApplications()->where('capability', $capability)->first(),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function store(Request $request, CapabilityReviewer $reviewer): RedirectResponse
    {
        $this->authorize('create', CapabilityApplication::class);

        $data = $request->validate([
            'capability' => ['required', Rule::enum(PartnerCapability::class)],
            'organization' => ['nullable', 'string', 'max:160'],
            'statement' => ['required', 'string', 'max:1000'],
        ]);

        $reviewer->submit(
            $request->user(),
            PartnerCapability::from($data['capability']),
            $data['organization'] ?? null,
            $data['statement'],
        );

        return redirect()
            ->route('account.applications.index')
            ->with('status', 'Application submitted. A decision has not been made.');
    }
}
