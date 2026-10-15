<?php

namespace App\Http\Controllers\Staff;

use App\Enums\EvidenceSensitivity;
use App\Enums\VerificationItemState;
use App\Enums\VerificationOverride;
use App\Http\Controllers\Controller;
use App\Models\EvidenceFile;
use App\Models\Property;
use App\Models\User;
use App\Models\VerificationItem;
use App\Services\Seller\SellerPortal;
use App\Services\Verification\VerificationDesk;
use App\Services\Verification\VerificationOperations;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('staff.verification'), 403);

        $properties = Property::query()->with('passport')->latest()->paginate(20);

        return view('staff.verification.index', [
            'properties' => $properties,
            'summary' => app(VerificationOperations::class)->summary(),
        ]);
    }

    public function show(Request $request, Property $property, VerificationDesk $desk): View
    {
        abort_unless($request->user()->hasPermission('staff.verification'), 403);

        $desk->seed($property);
        $property->load(['passport', 'verificationItems.reviewer', 'verificationItems.files', 'owners.user', 'ownershipDocuments.reviewer']);

        return view('staff.verification.show', [
            'property' => $property,
            'requirements' => app(SellerPortal::class)->documentRequirements($property),
            'states' => VerificationItemState::cases(),
            'sensitivities' => EvidenceSensitivity::cases(),
            'overrides' => VerificationOverride::cases(),
        ]);
    }

    public function previewEvidence(Request $request, EvidenceFile $evidenceFile, VerificationDesk $desk): Response
    {
        abort_unless($request->user()->hasPermission('staff.verification'), 403);

        return $desk->download($evidenceFile, $request->user(), $request->boolean('inline'));
    }

    public function review(Request $request, VerificationItem $verificationItem, VerificationDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.verification'), 403);

        $data = $request->validate([
            'state' => ['required', Rule::enum(VerificationItemState::class)],
            'evidence_note' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
            'review_due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $desk->review($verificationItem, $request->user(), $data);

        return ApprovalsReturn::back($request, 'verification', 'Checklist item updated.');
    }

    public function evidence(Request $request, VerificationItem $verificationItem, VerificationDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.verification'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'sensitivity' => ['required', Rule::enum(EvidenceSensitivity::class)],
        ]);

        $desk->storeEvidence($verificationItem, $request->user(), $data['file'], EvidenceSensitivity::from($data['sensitivity']));

        return back()->with('status', 'Evidence stored. The check is not verified until it is reviewed.');
    }

    public function grant(Request $request, EvidenceFile $evidenceFile, VerificationDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.verification'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'expires_at' => ['required', 'date', 'after:now'],
        ]);

        $grant = $desk->grant($evidenceFile, User::query()->findOrFail($data['user_id']), $request->user(), Carbon::parse($data['expires_at']));

        return back()->with('status', 'Document access granted until '.$grant->expires_at->toDayDateTimeString().'.');
    }

    public function override(Request $request, Property $property, VerificationDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.verification'), 403);

        $data = $request->validate([
            'override' => ['required', Rule::enum(VerificationOverride::class)],
            'critical_exception' => ['nullable', 'string', 'max:2000'],
        ]);

        $desk->setOverride($property->passport, VerificationOverride::from($data['override']), $data['critical_exception'] ?? null, $request->user());

        return ApprovalsReturn::back($request, 'verification', 'Verification override saved.');
    }
}
