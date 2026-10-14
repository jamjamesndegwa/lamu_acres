<?php

namespace App\Services\Access;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\MessageEvent;
use App\Enums\PartnerCapability;
use App\Events\ApplicationDecided;
use App\Models\CapabilityApplication;
use App\Models\User;
use App\Services\Communications\MessageDesk;
use Illuminate\Validation\ValidationException;

class CapabilityReviewer
{
    public function __construct(private RoleAssigner $roles) {}

    public function submit(User $user, PartnerCapability $capability, ?string $organization, string $statement): CapabilityApplication
    {
        $application = CapabilityApplication::query()->firstOrNew([
            'user_id' => $user->id,
            'capability' => $capability,
        ]);

        if ($application->exists && $application->status === CapabilityApplicationStatus::Approved) {
            throw ValidationException::withMessages([
                'capability' => 'This capability is already approved.',
            ]);
        }

        if ($application->exists && $application->status === CapabilityApplicationStatus::Suspended) {
            throw ValidationException::withMessages([
                'capability' => 'This capability is suspended.',
            ]);
        }

        $application->fill([
            'organization' => $organization,
            'statement' => $statement,
            'status' => CapabilityApplicationStatus::Pending,
            'decided_by' => null,
            'decided_at' => null,
            'decision_reason' => null,
        ])->save();

        return $application;
    }

    public function decide(User $actor, CapabilityApplication $application, CapabilityApplicationStatus $status, string $reason): void
    {
        if (! in_array($status, [
            CapabilityApplicationStatus::Approved,
            CapabilityApplicationStatus::Rejected,
            CapabilityApplicationStatus::InformationRequired,
            CapabilityApplicationStatus::Suspended,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Choose a staff decision.',
            ]);
        }

        $previous = $application->status;
        $applicant = $application->user()->firstOrFail();

        $application->forceFill([
            'status' => $status,
            'decision_reason' => $reason,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ])->save();

        if ($status === CapabilityApplicationStatus::Approved) {
            $this->roles->assign($applicant, $application->capability->value);
        }

        if ($status === CapabilityApplicationStatus::Approved && $application->capability === PartnerCapability::Broker) {
            app(MessageDesk::class)->send($applicant, MessageEvent::BrokerApproved, [
                'name' => $applicant->name,
                'title' => 'Broker workspace',
                'summary' => 'Your broker application is approved.',
                'url' => route('broker.dashboard'),
            ], 'application:'.$application->id);
        }

        if (
            $status === CapabilityApplicationStatus::Suspended
            || ($status === CapabilityApplicationStatus::Rejected && $previous === CapabilityApplicationStatus::Approved)
        ) {
            if ($applicant->hasRole($application->capability->value)) {
                $applicant->removeRole($application->capability->value);
            }
        }

        event(new ApplicationDecided($application));
    }
}
