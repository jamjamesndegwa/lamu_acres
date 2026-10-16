<?php

namespace App\Http\Controllers\Account;

use App\Enums\PartnerCapability;
use App\Http\Controllers\Controller;
use App\Models\CapabilityApplication;
use App\Services\Access\CapabilityReviewer;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CapabilityApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', CapabilityApplication::class);

        return view('account.applications.index', [
            'applications' => $request->user()->capabilityApplications()->orderBy('capability')->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $capability = PartnerCapability::tryFrom((string) $request->query('capability', ''));
        abort_if($capability === null, 404);

        if ($request->user() === null) {
            return redirect()->route('register', ['capability' => $capability->value]);
        }

        $this->authorize('create', CapabilityApplication::class);

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
            $request->only(['legal_name', 'applicant_type', 'earb_registration_number', 'certificate_expires_on']),
            array_filter($request->only(CapabilityReviewer::EVIDENCE_TYPES), fn (mixed $file): bool => $file instanceof UploadedFile),
        );

        return redirect()
            ->route('account.applications.index')
            ->with('status', 'Application submitted. A decision has not been made.');
    }

    public function evidence(Request $request, CapabilityApplication $application, string $kind): Response
    {
        $this->authorize('view', $application);
        abort_unless(in_array($kind, CapabilityReviewer::EVIDENCE_TYPES, true), 404);
        $file = $application->verification['evidence'][$kind] ?? null;
        abort_unless(is_array($file) && in_array($file['disk'] ?? null, ['local', 'r2_private'], true)
            && str_starts_with($file['path'] ?? '', 'partner-evidence/'.$application->user_id.'/')
            && ! str_contains($file['path'], '..'), 404);
        $extension = ($file['mime'] ?? '') === 'application/pdf' ? 'pdf' : (($file['mime'] ?? '') === 'image/png' ? 'png' : 'jpg');

        if ($request->boolean('preview')) {
            abort_unless(in_array($file['mime'] ?? null, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);

            return Storage::disk($file['disk'])->response($file['path'], $kind.'.'.$extension, [
                'Content-Type' => $file['mime'],
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Content-Security-Policy' => "frame-ancestors 'self'",
            ]);
        }

        return Storage::disk($file['disk'])->download($file['path'], $kind.'.'.$extension, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
