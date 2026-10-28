<?php

namespace App\Services\Access;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\MessageEvent;
use App\Enums\PartnerCapability;
use App\Events\ApplicationDecided;
use App\Models\CapabilityApplication;
use App\Models\User;
use App\Notifications\ApplicationSubmittedNotification;
use App\Services\Communications\MessageDesk;
use App\Services\Communications\StaffDeskNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CapabilityReviewer
{
    public const MAX_EVIDENCE_KILOBYTES = 2048;

    public const EVIDENCE_TYPES = ['identity', 'practising_certificate', 'business_document', 'representative_authority'];

    public function __construct(private RoleAssigner $roles) {}

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, UploadedFile>  $files
     */
    public function submit(User $user, PartnerCapability $capability, ?string $organization, string $statement, array $details = [], array $files = []): CapabilityApplication
    {
        $existing = $user->capabilityApplications()->where('capability', $capability)->first();
        if ($existing !== null) {
            if (! $user->can('update', $existing)) {
                throw ValidationException::withMessages(['capability' => 'This application cannot be resubmitted in its current status. Follow the application status page for the next step.']);
            }
        } else {
            abort_unless($user->can('create', CapabilityApplication::class), 403);
        }
        $broker = $capability === PartnerCapability::Broker;
        $type = $details['applicant_type'] ?? null;
        $requiredBusiness = $type === 'firm' || $type === 'company_representative';
        $requiredAuthority = in_array($type, ['authorized_representative', 'company_representative'], true);
        $data = Validator::make(array_merge($details, $files, ['organization' => $organization, 'statement' => $statement]), [
            'legal_name' => ['required', 'string', 'max:160'],
            'applicant_type' => ['required', Rule::in($broker ? ['individual', 'firm'] : ['individual_owner', 'authorized_representative', 'company_representative'])],
            'organization' => [$requiredBusiness ? 'required' : 'nullable', 'string', 'max:160'],
            'statement' => ['required', 'string', 'min:20', 'max:1000'],
            'earb_registration_number' => [$broker ? 'required' : 'nullable', 'string', 'max:80'],
            'certificate_expires_on' => [$broker ? 'required' : 'nullable', 'date', 'after_or_equal:'.now('Africa/Nairobi')->toDateString()],
            'identity' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_EVIDENCE_KILOBYTES],
            'practising_certificate' => [$broker ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_EVIDENCE_KILOBYTES],
            'business_document' => [$requiredBusiness ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_EVIDENCE_KILOBYTES],
            'representative_authority' => [$requiredAuthority ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_EVIDENCE_KILOBYTES],
        ])->validate();
        $stored = [];

        try {
            return DB::transaction(function () use ($user, $capability, $broker, $data, &$stored): CapabilityApplication {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $application = CapabilityApplication::query()->where('user_id', $user->id)->where('capability', $capability)->lockForUpdate()->first();
                if ($application !== null && ! $user->can('update', $application)) {
                    throw ValidationException::withMessages(['capability' => 'This application cannot be resubmitted in its current status. Follow the application status page for the next step.']);
                }
                $application ??= new CapabilityApplication(['user_id' => $user->id, 'capability' => $capability]);
                $privateBucket = config('filesystems.disks.r2_private.bucket');
                $disk = filled($privateBucket) || filled(config('filesystems.disks.r2.bucket')) ? 'r2_private' : 'local';
                foreach (self::EVIDENCE_TYPES as $kind) {
                    if (($data[$kind] ?? null) instanceof UploadedFile) {
                        $path = $data[$kind]->store('partner-evidence/'.$user->id, $disk);
                        if ($path === false) {
                            throw ValidationException::withMessages([$kind => 'The private evidence could not be stored.']);
                        }
                        $stored[$kind] = ['disk' => $disk, 'path' => $path, 'mime' => $data[$kind]->getMimeType(), 'sha256' => hash_file('sha256', $data[$kind]->getRealPath())];
                    }
                }
                $history = $application->verification_history ?? [];
                if ($application->exists) {
                    $history[] = ['event' => 'resubmitted', 'actor_id' => $user->id, 'at' => now()->toIso8601String(), 'previous_status' => $application->status->value, 'previous_verification' => $application->verification, 'previous_decision' => ['actor_id' => $application->decided_by, 'at' => $application->decided_at?->toIso8601String(), 'reason' => $application->decision_reason]];
                }
                $history[] = ['event' => 'submitted', 'actor_id' => $user->id, 'at' => now()->toIso8601String()];
                $application->forceFill([
                    'organization' => $data['organization'] ?? null, 'statement' => $data['statement'],
                    'status' => CapabilityApplicationStatus::Pending, 'decided_by' => null, 'decided_at' => null, 'decision_reason' => null,
                    'verification' => [
                        'legal_name' => trim($data['legal_name']), 'applicant_type' => $data['applicant_type'],
                        'earb_registration_number' => $broker ? trim($data['earb_registration_number']) : null,
                        'certificate_expires_on' => $broker ? Carbon::parse($data['certificate_expires_on'])->toDateString() : null,
                        'evidence' => $stored, 'identity' => ['outcome' => 'pending'], 'licence' => ['outcome' => 'pending'],
                    ],
                    'verification_history' => $history,
                ])->save();

                $resubmitted = collect($history)->contains(
                    fn (array $entry): bool => ($entry['event'] ?? null) === 'resubmitted',
                );
                if ($resubmitted && $capability === PartnerCapability::Broker) {
                    $user->assignRole('broker');
                }

                $capabilityLabel = $capability->label();
                $submitEvent = $resubmitted ? MessageEvent::ApplicationResubmitted : MessageEvent::ApplicationSubmitted;
                $historyCount = count($history);

                app(MessageDesk::class)->send($user, $submitEvent, [
                    'name' => $user->name,
                    'title' => $capabilityLabel.' application received',
                    'reference' => (string) $application->id,
                    'summary' => $resubmitted
                        ? 'Your '.$capabilityLabel.' application was resubmitted and is awaiting staff review.'
                        : 'Your '.$capabilityLabel.' application was received and is awaiting staff review.',
                    'url' => route('account.applications.index'),
                ], 'application:'.$application->id.':submit:'.$historyCount);

                $staffMessage = $resubmitted
                    ? 'A partner resubmitted a '.$capabilityLabel.' application for review.'
                    : 'A new '.$capabilityLabel.' partner application was submitted for review.';

                app(StaffDeskNotifier::class)->notifyPermission(
                    'staff.brokerage',
                    MessageEvent::ApplicationStaffAlert,
                    [
                        'title' => $capabilityLabel.' application',
                        'reference' => (string) $application->id,
                        'summary' => $staffMessage,
                        'url' => route('staff.applications.edit', ['application' => $application]),
                    ],
                    'application:'.$application->id.':staff:'.$historyCount,
                    new ApplicationSubmittedNotification($application),
                    $user->id,
                );

                return $application;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $verification */
    public function decide(User $actor, CapabilityApplication $application, CapabilityApplicationStatus $status, string $reason, array $verification = []): void
    {
        abort_unless($actor->can('decide', $application), 403);
        Validator::make(['status' => $status->value, 'decision_reason' => $reason], [
            'status' => [Rule::in(['approved', 'rejected', 'information_required', 'suspended'])],
            'decision_reason' => ['required', 'string', 'min:8', 'max:500'],
        ])->validate();
        $previous = $application->fresh();
        $notify = $previous->status !== $status || $previous->decision_reason !== trim($reason);
        if (! $notify && $verification === []) {
            return;
        }
        $decision = DB::transaction(function () use ($actor, $application, $status, $reason, $verification): CapabilityApplication {
            $locked = CapabilityApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;
            $profile = $locked->verification ?? [];
            foreach (['identity', 'licence'] as $check) {
                if ($check === 'licence' && $locked->capability !== PartnerCapability::Broker) {
                    continue;
                }
                if (! isset($verification[$check.'_outcome']) || $verification[$check.'_outcome'] === '') {
                    continue;
                }
                $record = Validator::make($verification, [
                    $check.'_outcome' => ['required', Rule::in(['verified', 'information_required', 'rejected'])],
                ])->validate();
                $profile[$check] = [
                    'outcome' => $record[$check.'_outcome'],
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now()->toIso8601String(),
                    'verified_at' => $record[$check.'_outcome'] === 'verified' ? now()->toIso8601String() : null,
                    'reference' => null,
                    'next_review_on' => null,
                    'authoritative_checked' => false,
                    'contact_fingerprint' => null,
                ];
            }
            if ($status === CapabilityApplicationStatus::Suspended) {
                $profile['identity']['outcome'] = 'pending';
                if ($locked->capability === PartnerCapability::Broker) {
                    $profile['licence']['outcome'] = 'pending';
                }
            }
            $locked->verification = $profile;
            if ($status === CapabilityApplicationStatus::Approved) {
                foreach ($profile['evidence'] ?? [] as $file) {
                    try {
                        $available = in_array($file['disk'] ?? null, ['local', 'r2_private'], true)
                            && str_starts_with($file['path'] ?? '', 'partner-evidence/'.$locked->user_id.'/')
                            && ! str_contains($file['path'], '..')
                            && Storage::disk($file['disk'])->exists($file['path']);
                    } catch (\Throwable) {
                        $available = false;
                    }
                    if (! $available) {
                        throw ValidationException::withMessages(['status' => 'Private supporting evidence is unavailable. Ask the applicant to upload current evidence before approval.']);
                    }
                }
            }
            if ($status === CapabilityApplicationStatus::Approved && ($blockers = $this->verificationBlockers($locked)) !== []) {
                throw ValidationException::withMessages(['status' => implode(' ', $blockers)]);
            }
            $history = $locked->verification_history ?? [];
            if ($history === [] && $locked->decided_at !== null) {
                $history[] = ['event' => 'existing_decision', 'actor_id' => $locked->decided_by, 'at' => $locked->decided_at->toIso8601String(), 'reason' => $locked->decision_reason, 'status' => $previous->value];
            }
            $history[] = ['event' => 'staff_decision', 'actor_id' => $actor->id, 'at' => now()->toIso8601String(), 'from' => $previous->value, 'to' => $status->value, 'reason' => trim($reason), 'checks' => ['identity' => $profile['identity'] ?? null, 'licence' => $profile['licence'] ?? null]];
            $locked->forceFill(['status' => $status, 'decision_reason' => trim($reason), 'decided_by' => $actor->id, 'decided_at' => now(), 'verification_history' => $history])->save();
            $applicant = $locked->user;
            if ($status === CapabilityApplicationStatus::Approved) {
                $this->roles->assign($applicant, $locked->capability->value);
            } elseif ($previous === CapabilityApplicationStatus::Approved || $status === CapabilityApplicationStatus::Suspended) {
                $applicant->removeRole($locked->capability->value);
            }

            return $locked;
        });
        $application->refresh();
        if (! $notify) {
            return;
        }
        event(new ApplicationDecided($decision));
    }

    /** @return list<string> */
    public function verificationBlockers(CapabilityApplication $application): array
    {
        $profile = $application->verification ?? [];
        $issues = [];
        $evidence = $profile['evidence'] ?? [];
        if (blank($profile['legal_name'] ?? null) || blank($profile['applicant_type'] ?? null) || empty($evidence['identity'])) {
            $issues[] = 'Provide the legal name, applicant type and private identity evidence.';
        }
        $type = $profile['applicant_type'] ?? null;
        $types = $application->capability === PartnerCapability::Broker ? ['individual', 'firm'] : ['individual_owner', 'authorized_representative', 'company_representative'];
        if (! in_array($type, $types, true)) {
            $issues[] = 'Select an applicant type valid for this capability.';
        }
        if (in_array($type, ['firm', 'company_representative'], true) && (blank($application->organization) || empty($evidence['business_document']))) {
            $issues[] = 'Provide the organisation name and applicable business documentation.';
        }
        if (in_array($type, ['authorized_representative', 'company_representative'], true) && empty($evidence['representative_authority'])) {
            $issues[] = 'Provide the representative authority document.';
        }
        if (! $this->checkIsCurrent($profile['identity'] ?? [], $application)) {
            $issues[] = 'Mark identity as verified after reviewing the uploaded ID or passport.';
        }
        if ($application->capability === PartnerCapability::Broker) {
            if (blank($profile['earb_registration_number'] ?? null) || empty($evidence['practising_certificate']) || ! $this->dateIsCurrent($profile['certificate_expires_on'] ?? null)) {
                $issues[] = 'The broker application must include an EARB registration number, practising certificate, and a valid certificate expiry date.';
            }
            $licence = $profile['licence'] ?? [];
            if (! $this->checkIsCurrent($licence, $application)) {
                $issues[] = 'Mark professional standing as verified after reviewing the certificate and registration details.';
            }
        }

        return $issues;
    }

    /**
     * @return list<array{key: string, label: string, state: string, detail: string}>
     */
    public function reviewChecklist(CapabilityApplication $application): array
    {
        $profile = $application->verification ?? [];
        $evidence = $profile['evidence'] ?? [];
        $type = $profile['applicant_type'] ?? null;
        $isBroker = $application->capability === PartnerCapability::Broker;
        $items = [];

        $items[] = $this->checklistItem(
            'legal_name',
            'Legal name and applicant type',
            filled($profile['legal_name'] ?? null) && filled($type),
            filled($profile['legal_name'] ?? null) ? ($profile['legal_name'].' · '.str_replace('_', ' ', (string) $type)) : 'Missing from application',
        );
        $items[] = $this->checklistItem(
            'identity_document',
            'Identity document uploaded',
            ! empty($evidence['identity']),
            ! empty($evidence['identity']) ? 'Ready for staff review' : 'Applicant must upload ID or passport',
        );
        $items[] = $this->checklistItem(
            'identity_review',
            'Identity reviewed by staff',
            $this->checkIsCurrent($profile['identity'] ?? [], $application),
            ($profile['identity']['outcome'] ?? null) === 'verified' ? 'Verified' : 'Set outcome under Identity documents',
        );

        if (in_array($type, ['firm', 'company_representative'], true)) {
            $items[] = $this->checklistItem(
                'business_document',
                'Business registration document',
                filled($application->organization) && ! empty($evidence['business_document']),
                filled($application->organization) ? $application->organization : 'Organisation name and document required',
            );
        }

        if (in_array($type, ['authorized_representative', 'company_representative'], true)) {
            $items[] = $this->checklistItem(
                'representative_authority',
                'Representative authority document',
                ! empty($evidence['representative_authority']),
                ! empty($evidence['representative_authority']) ? 'Uploaded' : 'Required for representatives',
            );
        }

        if ($isBroker) {
            $items[] = $this->checklistItem(
                'earb_details',
                'EARB registration and certificate expiry',
                filled($profile['earb_registration_number'] ?? null) && $this->dateIsCurrent($profile['certificate_expires_on'] ?? null),
                trim(($profile['earb_registration_number'] ?? 'No registration').' · expires '.($profile['certificate_expires_on'] ?? 'unknown')),
            );
            $items[] = $this->checklistItem(
                'practising_certificate',
                'Practising certificate uploaded',
                ! empty($evidence['practising_certificate']),
                ! empty($evidence['practising_certificate']) ? 'Ready for staff review' : 'Certificate file missing',
            );
            $items[] = $this->checklistItem(
                'licence_review',
                'Professional standing reviewed by staff',
                $this->checkIsCurrent($profile['licence'] ?? [], $application),
                ($profile['licence']['outcome'] ?? null) === 'verified' ? 'Verified' : 'Set outcome under Professional standing',
            );
        }

        return $items;
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function checklistItem(string $key, string $label, bool $complete, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'state' => $complete ? 'complete' : 'pending',
            'detail' => $detail,
        ];
    }

    public function hasCurrentCapability(User $user, PartnerCapability $capability): bool
    {
        $application = $user->capabilityApplications()->where('capability', $capability)->first();

        return ! $user->isSuspended() && $application !== null && $application->status === CapabilityApplicationStatus::Approved && $this->verificationBlockers($application) === [];
    }

    public function assertBrokerCanAct(User $user): void
    {
        if (! $user->hasPermission('workspace.broker') || ! $this->hasCurrentCapability($user, PartnerCapability::Broker)) {
            throw ValidationException::withMessages(['capability' => 'Broker credentials are unverified, expired, due for review or suspended. Submit current evidence through Partner applications and ask staff to review it. Existing contracts and historical earnings remain available.']);
        }
    }

    /** @param array<string, mixed> $check */
    private function checkIsCurrent(array $check, CapabilityApplication $application): bool
    {
        if (($check['outcome'] ?? null) !== 'verified' || blank($check['verified_at'] ?? null)
            || empty($check['reviewed_by'])
            || (int) $check['reviewed_by'] === (int) $application->user_id) {
            return false;
        }

        $nextReview = $check['next_review_on'] ?? null;

        if (blank($nextReview)) {
            return true;
        }

        return $this->dateIsCurrent($nextReview);
    }

    private function dateIsCurrent(?string $date): bool
    {
        if (blank($date)) {
            return false;
        }
        try {
            return Carbon::parse($date, 'Africa/Nairobi')->endOfDay()->gte(now());
        } catch (\Throwable) {
            return false;
        }
    }

    private function contactFingerprint(User $user): ?string
    {
        $contact = $user->email_verified_at !== null ? $user->email : ($user->phone_verified_at !== null ? $user->phone : null);

        return filled($contact) ? hash('sha256', (string) $contact) : null;
    }
}
