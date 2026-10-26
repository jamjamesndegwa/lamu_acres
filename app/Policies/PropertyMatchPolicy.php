<?php

namespace App\Policies;

use App\Enums\PropertyMatchStatus;
use App\Models\PropertyMatch;
use App\Models\User;

class PropertyMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canUseBuyerFeatures()
            || $user->hasPermission('staff.buyers')
            || $user->hasPermission('staff.sourcing');
    }

    public function view(User $user, PropertyMatch $propertyMatch): bool
    {
        if ($propertyMatch->isOwnedBy($user) && $user->canUseBuyerFeatures()) {
            return true;
        }

        if ($user->hasPermission('staff.buyers') || $user->hasPermission('staff.sourcing')) {
            return true;
        }

        return (int) $propertyMatch->assigned_to === (int) $user->id;
    }

    public function reject(User $user, PropertyMatch $propertyMatch): bool
    {
        return $propertyMatch->isOwnedBy($user)
            && $user->canUseBuyerFeatures()
            && $propertyMatch->status === PropertyMatchStatus::Shortlisted;
    }

    public function requestVisit(User $user, PropertyMatch $propertyMatch): bool
    {
        return $this->reject($user, $propertyMatch);
    }

    public function offer(User $user, PropertyMatch $propertyMatch): bool
    {
        return $this->reject($user, $propertyMatch);
    }

    public function requestDocuments(User $user, PropertyMatch $propertyMatch): bool
    {
        return $this->reject($user, $propertyMatch);
    }
}
