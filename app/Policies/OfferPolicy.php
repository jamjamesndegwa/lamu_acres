<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canUseBuyerFeatures()
            || $user->hasPermission('staff.buyers')
            || $user->hasPermission('staff.sales');
    }

    public function view(User $user, Offer $offer): bool
    {
        if ($offer->isOwnedBy($user) && $user->canUseBuyerFeatures()) {
            return true;
        }

        return $user->hasPermission('staff.buyers') || $user->hasPermission('staff.sales');
    }

    public function updateStatus(User $user, Offer $offer): bool
    {
        return ! $offer->isOwnedBy($user)
            && ($user->hasPermission('staff.buyers') || $user->hasPermission('staff.sales'));
    }

    public function withdraw(User $user, Offer $offer): bool
    {
        return $offer->isOwnedBy($user)
            && $user->canUseBuyerFeatures()
            && $offer->isOpen();
    }
}
