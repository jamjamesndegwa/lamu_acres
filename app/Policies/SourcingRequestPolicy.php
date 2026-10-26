<?php

namespace App\Policies;

use App\Enums\SourcingRequestStatus;
use App\Models\SourcingRequest;
use App\Models\User;

class SourcingRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.sourcing')
            || $user->canUseBuyerFeatures()
            || SourcingRequest::query()->where('assigned_to', $user->id)->exists();
    }

    public function view(User $user, SourcingRequest $sourcingRequest): bool
    {
        if ($sourcingRequest->isOwnedBy($user) && $user->canUseBuyerFeatures()) {
            return true;
        }

        if ($user->hasPermission('staff.sourcing')) {
            return true;
        }

        return $user->hasPermission('workspace.staff')
            && (int) $sourcingRequest->assigned_to === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->canUseBuyerFeatures();
    }

    public function update(User $user, SourcingRequest $sourcingRequest): bool
    {
        return $sourcingRequest->isOwnedBy($user)
            && $user->canUseBuyerFeatures()
            && $sourcingRequest->status !== SourcingRequestStatus::Closed;
    }

    public function assign(User $user, SourcingRequest $sourcingRequest): bool
    {
        return $user->hasPermission('staff.sourcing');
    }

    public function updateStatus(User $user, SourcingRequest $sourcingRequest): bool
    {
        return $user->hasPermission('staff.sourcing')
            || (int) $sourcingRequest->assigned_to === (int) $user->id;
    }
}
