<?php

namespace App\Policies;

use App\Models\BuyerProfile;
use App\Models\User;

class BuyerProfilePolicy
{
    public function view(User $user, BuyerProfile $profile): bool
    {
        return $user->is($profile->user) || $user->hasPermission('staff.buyers');
    }

    public function create(User $user): bool
    {
        return $user->canUseBuyerFeatures();
    }

    public function update(User $user, BuyerProfile $profile): bool
    {
        return ! $user->isSuspended() && $user->is($profile->user);
    }
}
