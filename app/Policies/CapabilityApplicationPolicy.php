<?php

namespace App\Policies;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\PartnerCapability;
use App\Models\CapabilityApplication;
use App\Models\User;
use App\Services\Access\CapabilityReviewer;

class CapabilityApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isSuspended() && (
            $this->canApply($user)
            || $user->hasPermission('workspace.staff')
            || $user->hasPermission('workspace.admin')
        );
    }

    public function view(User $user, CapabilityApplication $application): bool
    {
        return $user->is($application->user) || $this->canReview($user);
    }

    public function create(User $user): bool
    {
        return $this->canApply($user);
    }

    public function update(User $user, CapabilityApplication $application): bool
    {
        if (! $user->is($application->user)) {
            return false;
        }

        if (in_array($application->status, [CapabilityApplicationStatus::InformationRequired, CapabilityApplicationStatus::Rejected], true)) {
            return true;
        }

        if (! $this->canApply($user)) {
            return false;
        }

        return $application->capability === PartnerCapability::Broker
            && $application->status === CapabilityApplicationStatus::Approved
            && app(CapabilityReviewer::class)->verificationBlockers($application) !== [];
    }

    public function decide(User $user, CapabilityApplication $application): bool
    {
        return $this->canReview($user) && ! $user->is($application->user);
    }

    private function canReview(User $user): bool
    {
        return ! $user->isSuspended() && $user->hasPermission('staff.brokerage');
    }

    private function canApply(User $user): bool
    {
        if ($user->isSuspended()
            || $user->hasPermission('workspace.staff')
            || $user->hasPermission('workspace.admin')) {
            return false;
        }

        return $user->hasPermission('workspace.buyer')
            || $user->hasPermission('workspace.seller')
            || $user->hasPermission('workspace.broker');
    }
}
