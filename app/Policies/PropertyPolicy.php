<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(?User $user, Property $property): bool
    {
        return $property->isPublished();
    }

    public function save(User $user, Property $property): bool
    {
        return $property->isPublished() && $user->hasPermission('workspace.buyer');
    }

    public function enquire(User $user, Property $property): bool
    {
        return $this->save($user, $property);
    }
}
