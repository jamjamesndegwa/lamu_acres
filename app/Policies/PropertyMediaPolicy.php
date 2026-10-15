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

        $property = $media->property;

        if ($property === null
            || (int) $media->uploaded_by !== (int) $user->id
            || (int) $property->submitted_by !== (int) $user->id) {
            return false;
        }

        if ($property->submittedBySeller()) {
            return $property->isEditableBySubmitter();
        }

        return true;
    }

    public function viewOriginal(User $user, PropertyMedia $media): bool
    {
        return $this->delete($user, $media);
    }
}
