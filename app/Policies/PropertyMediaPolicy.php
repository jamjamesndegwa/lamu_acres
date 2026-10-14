<?php

namespace App\Policies;

use App\Models\PropertyMedia;
use App\Models\User;

class PropertyMediaPolicy
{
    public function delete(User $user, PropertyMedia $media): bool
    {
        if ($user->hasPermission('staff.properties')) {
            return true;
        }

        return (int) $media->uploaded_by === (int) $user->id
            && (int) $media->property?->submitted_by === (int) $user->id;
    }

    public function viewOriginal(User $user, PropertyMedia $media): bool
    {
        return $this->delete($user, $media);
    }
}
