<?php

namespace App\Http\Controllers\Staff;

use App\Enums\CapabilityApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\CapabilityApplication;
use App\Models\User;
use App\Notifications\AccountEmailChanged;
use App\Services\Access\CapabilityReviewer;
use App\Support\ApprovalsReturn;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'application' => $application->load('user:id,name,email,email_verified_at,phone,phone_verified_at,pending_phone,suspended_at'),
            'verificationBlockers' => app(CapabilityReviewer::class)->verificationBlockers($application),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function verifyContact(Request $request, CapabilityApplication $application): RedirectResponse
    {
        $this->authorize('decide', $application);
        abort_unless(config('lamu.allow_manual_contact_verification', false), 403);
        $data = $request->validate([
            'contact_channel' => ['required', Rule::in(['email', 'phone'])],
            'contact_value' => ['required', 'string', 'max:255'],
            'verification_reference' => ['required', 'string', 'min:8', 'max:500'],
            'contact_confirmed' => ['accepted'],
        ]);
        DB::transaction(function () use ($application, $request, $data): void {
            $locked = CapabilityApplication::query()->lockForUpdate()->findOrFail($application->id);
            $this->authorize('decide', $locked);
            $applicant = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            abort_if($applicant->isSuspended(), 403);
            $channel = $data['contact_channel'];
            $value = $applicant->getAttribute($channel);
            $timestamp = $channel.'_verified_at';
            if (blank($value) || ! hash_equals((string) $value, $data['contact_value'])) {
                throw ValidationException::withMessages(['contact_value' => 'The saved contact has changed or is missing. Refresh this page and verify the current contact.']);
            }
            if ($applicant->getAttribute($timestamp) !== null) {
                throw ValidationException::withMessages(['contact_value' => 'This contact is already verified.']);
            }
            $at = now();
            $applicant->forceFill([$timestamp => $at])->save();
            $history = $locked->verification_history ?? [];
            $history[] = [
                'event' => 'manual_contact_verified', 'method' => 'staff_recorded',
                'actor_id' => $request->user()->id, 'at' => $at->toIso8601String(),
                'channel' => $channel, 'contact_hash' => hash('sha256', (string) $value),
                'reference' => trim($data['verification_reference']),
            ];
            $locked->forceFill(['verification_history' => $history])->save();
        });

        return back()->with('status', 'Staff-recorded contact verification saved. Identity, professional standing and the capability decision still require review.');
    }

    public function requestEmailVerification(Request $request, CapabilityApplication $application): RedirectResponse
    {
        $this->authorize('decide', $application);
        $applicant = $application->user;
        abort_if($applicant->isSuspended(), 403);
        if ($applicant->email_verified_at !== null) {
            return back()->with('status', 'The applicant email is already verified.');
        }
        if (! AccountEmailChanged::canDeliverEmail()) {
            throw ValidationException::withMessages(['email_delivery' => 'Email delivery is not configured. Configure a delivery mailer before sending verification emails; the log driver does not deliver to an inbox.']);
        }
        $applicant->notify(new AccountEmailChanged(emailChanged: false));
        DB::transaction(function () use ($application, $request): void {
            $locked = CapabilityApplication::query()->lockForUpdate()->findOrFail($application->id);
            $history = $locked->verification_history ?? [];
            $history[] = ['event' => 'email_verification_requested', 'actor_id' => $request->user()->id, 'at' => now()->toIso8601String()];
            $locked->forceFill(['verification_history' => $history])->save();
        });

        return back()->with('status', 'Verification email sent to the applicant. They must open the email link while signed in. Refresh this review after they confirm.');
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
            $request->only(['identity_outcome', 'identity_reference', 'identity_next_review_on', 'licence_outcome', 'licence_reference', 'licence_next_review_on', 'earb_checked']),
        );

        return ApprovalsReturn::orRedirect(
            $request,
            'applications',
            'Application decision recorded.',
            route('staff.applications.index'),
        );
    }
}
