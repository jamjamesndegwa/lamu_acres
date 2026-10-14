<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

class EnquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.buyers')
            || $user->assignedEnquiries()->exists();
    }

    public function view(User $user, Enquiry $enquiry): bool
    {
        if ($enquiry->isOwnedBy($user) && $user->hasPermission('workspace.buyer')) {
            return true;
        }

        if ($user->hasPermission('staff.buyers')) {
            return true;
        }

        return $user->hasPermission('workspace.staff')
            && (int) $enquiry->assigned_to === (int) $user->id;
    }

    public function respond(User $user, Enquiry $enquiry): bool
    {
        if ($enquiry->isOwnedBy($user) || ! $user->hasPermission('workspace.staff')) {
            return false;
        }

        return $user->hasPermission('staff.buyers')
            || (int) $enquiry->assigned_to === (int) $user->id;
    }

    public function assign(User $user, Enquiry $enquiry): bool
    {
        return $user->hasPermission('staff.buyers');
    }

    public function updateLead(User $user, Enquiry $enquiry): bool
    {
        return $this->respond($user, $enquiry);
    }
}
