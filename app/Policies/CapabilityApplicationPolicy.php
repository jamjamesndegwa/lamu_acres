<?php

namespace App\Policies;

use App\Models\CapabilityApplication;
use App\Models\User;

class CapabilityApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canReview($user);
    }

    public function view(User $user, CapabilityApplication $application): bool
    {
        return $user->is($application->user) || $this->canReview($user);
    }

    public function create(User $user): bool
    {
        return ! $user->isSuspended();
    }

    public function update(User $user, CapabilityApplication $application): bool
    {
        return $user->is($application->user)
            && ! $user->isSuspended()
            && $application->status->isOpenForApplicant();
    }

    public function decide(User $user, CapabilityApplication $application): bool
    {
        return $this->canReview($user) && ! $user->is($application->user);
    }

    private function canReview(User $user): bool
    {
        return ! $user->isSuspended() && $user->hasPermission('staff.brokerage');
    }
}
