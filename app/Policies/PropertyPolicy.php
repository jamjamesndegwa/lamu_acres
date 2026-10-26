<?php

namespace App\Policies;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(?User $user, Property $property): bool
    {
        if ($property->isPublished()) {
            return true;
        }

        return $user?->hasPermission('staff.properties') ?? false;
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('staff.properties');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Property $property): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->manage($user) && $property->status === PropertyStatus::Draft;
    }

    public function save(User $user, Property $property): bool
    {
        return $property->isPublished() && $user->canUseBuyerFeatures();
    }

    public function enquire(User $user, Property $property): bool
    {
        return $this->save($user, $property);
    }

    public function visit(User $user, Property $property): bool
    {
        return $this->save($user, $property);
    }

    public function requestDocuments(User $user, Property $property): bool
    {
        return $this->save($user, $property);
    }

    public function manageMedia(User $user, Property $property): bool
    {
        if ($user->hasPermission('staff.properties')) {
            return true;
        }

        if ((int) $property->submitted_by !== (int) $user->id) {
            return false;
        }

        if ($user->canUseBrokerFeatures() && (int) $property->submitted_by === (int) $user->id) {
            return true;
        }

        return $user->canUseSellerFeatures()
            && $user->hasPermission('workspace.seller')
            && $property->isEditableBySubmitter();
    }

    public function submit(User $user): bool
    {
        return $user->canUseBrokerFeatures();
    }

    public function viewSubmission(User $user, Property $property): bool
    {
        if ($property->submitted_by === null) {
            return false;
        }

        return $property->submitted_by === $user->id || $user->hasPermission('staff.properties');
    }

    public function reviewSubmission(User $user, Property $property): bool
    {
        return $property->submitted_by !== null && $user->hasPermission('staff.properties');
    }
}
