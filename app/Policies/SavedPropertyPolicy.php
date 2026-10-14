<?php

namespace App\Policies;

use App\Models\SavedProperty;
use App\Models\User;

class SavedPropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workspace.buyer');
    }

    public function delete(User $user, SavedProperty $savedProperty): bool
    {
        return $savedProperty->user_id === $user->id;
    }
}
