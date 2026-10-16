<?php

namespace App\Services\Access;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\MessageEvent;
use App\Enums\PartnerCapability;
use App\Events\ApplicationDecided;
use App\Models\CapabilityApplication;
use App\Models\User;
use App\Services\Communications\MessageDesk;
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
        abort_unless($user->can('create', CapabilityApplication::class), 403);
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
                if ($application !== null && ($application->status === CapabilityApplicationStatus::Suspended
                    || ($application->status === CapabilityApplicationStatus::Approved && ! $broker))) {
                    throw ValidationException::withMessages(['capability' => 'This seller capability is already approved, or the capability is suspended. Ask staff to reopen a suspended application. Broker credentials may be resubmitted for renewal.']);
                }
                $application ??= new CapabilityApplication(['user_id' => $user->id, 'capability' => $capability]);
                $privateBucket = config('filesystems.disks.r2_private.bucket');
                $disk = filled($privateBucket) && $privateBucket !== config('filesystems.disks.r2.bucket') ? 'r2_private' : 'local';
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
                    $check.'_reference' => ['required', 'string', 'min:8', 'max:500'],
                    $check.'_next_review_on' => ['required_if:'.$check.'_outcome,verified', 'nullable', 'date', 'after_or_equal:'.now('Africa/Nairobi')->toDateString()],
                    'earb_checked' => $check === 'licence' && $verification[$check.'_outcome'] === 'verified' ? ['accepted'] : ['nullable'],
                ])->validate();
                $profile[$check] = [
                    'outcome' => $record[$check.'_outcome'], 'reviewed_by' => $actor->id,
                    'reviewed_at' => now()->toIso8601String(),
                    'verified_at' => $record[$check.'_outcome'] === 'verified' ? now()->toIso8601String() : null, 'reference' => $record[$check.'_reference'],
                    'next_review_on' => $record[$check.'_next_review_on'] ?? null,
                    'authoritative_checked' => $check === 'licence' && ($record['earb_checked'] ?? false),
                    'contact_fingerprint' => $this->contactFingerprint($locked->user),
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
        if ($status === CapabilityApplicationStatus::Approved && $decision->capability === PartnerCapability::Broker) {
            app(MessageDesk::class)->send($decision->user, MessageEvent::BrokerApproved, [
                'name' => $decision->user->name, 'title' => 'Broker workspace', 'summary' => 'Your broker application is approved.', 'url' => route('broker.dashboard'),
            ], 'application:'.$decision->id);
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
            $issues[] = 'Staff must complete identity verification with an evidence reference and next review date.';
        }
        if ($application->capability === PartnerCapability::Broker) {
            if ($this->contactFingerprint($application->user) === null) {
                $issues[] = 'Verify the account email or phone before granting broker capability.';
            }
            if (blank($profile['earb_registration_number'] ?? null) || empty($evidence['practising_certificate']) || ! $this->dateIsCurrent($profile['certificate_expires_on'] ?? null)) {
                $issues[] = 'Provide an EARB registration number and a current practising certificate with a valid expiry date.';
            }
            $licence = $profile['licence'] ?? [];
            if (! $this->checkIsCurrent($licence, $application) || empty($licence['authoritative_checked'])
                || ($licence['contact_fingerprint'] ?? null) !== $this->contactFingerprint($application->user)) {
                $issues[] = 'Staff must verify professional standing against authoritative EARB records and record the reference and next review date.';
            }
        }

        return $issues;
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
        return ($check['outcome'] ?? null) === 'verified' && filled($check['verified_at'] ?? null)
            && filled($check['reference'] ?? null) && ! empty($check['reviewed_by'])
            && (int) $check['reviewed_by'] !== (int) $application->user_id && $this->dateIsCurrent($check['next_review_on'] ?? null);
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
