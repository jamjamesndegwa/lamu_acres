<?php

namespace App\Policies;

use App\Models\DocumentAccessRequest;
use App\Models\User;

class DocumentAccessRequestPolicy
{
    public function view(User $user, DocumentAccessRequest $documentAccessRequest): bool
    {
        if ($documentAccessRequest->isOwnedBy($user) && $user->canUseBuyerFeatures()) {
            return true;
        }

        return $user->hasPermission('staff.buyers');
    }

    public function decide(User $user, DocumentAccessRequest $documentAccessRequest): bool
    {
        return $user->hasPermission('staff.buyers');
    }
}
