<?php

namespace App\Policies;

use App\Models\BrokerIntroduction;
use App\Models\User;

class BrokerIntroductionPolicy
{
    public function view(User $user, BrokerIntroduction $introduction): bool
    {
        return $introduction->broker_id === $user->id || $user->hasPermission('staff.brokerage');
    }

    public function create(User $user): bool
    {
        return ! $user->isSuspended() && $user->hasPermission('workspace.broker');
    }

    public function review(User $user, BrokerIntroduction $introduction): bool
    {
        return $user->hasPermission('staff.brokerage');
    }
}
