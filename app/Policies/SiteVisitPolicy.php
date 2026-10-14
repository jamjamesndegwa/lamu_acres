<?php

namespace App\Policies;

use App\Enums\SiteVisitStatus;
use App\Models\SiteVisit;
use App\Models\User;

class SiteVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workspace.buyer')
            || $user->hasPermission('staff.buyers')
            || SiteVisit::query()->where('assigned_to', $user->id)->exists();
    }

    public function view(User $user, SiteVisit $siteVisit): bool
    {
        if ($siteVisit->isOwnedBy($user) && $user->hasPermission('workspace.buyer')) {
            return true;
        }

        if ($user->hasPermission('staff.buyers')) {
            return true;
        }

        return (int) $siteVisit->assigned_to === (int) $user->id;
    }

    public function assign(User $user, SiteVisit $siteVisit): bool
    {
        return $user->hasPermission('staff.buyers');
    }

    public function manage(User $user, SiteVisit $siteVisit): bool
    {
        if ($siteVisit->isOwnedBy($user)) {
            return false;
        }

        return $user->hasPermission('staff.buyers')
            || (int) $siteVisit->assigned_to === (int) $user->id;
    }

    public function cancel(User $user, SiteVisit $siteVisit): bool
    {
        if (! in_array($siteVisit->status, [SiteVisitStatus::Requested, SiteVisitStatus::Confirmed], true)) {
            return false;
        }

        return ($siteVisit->isOwnedBy($user) && $user->hasPermission('workspace.buyer'))
            || $this->manage($user, $siteVisit);
    }

    public function acknowledge(User $user, SiteVisit $siteVisit): bool
    {
        return $siteVisit->isOwnedBy($user)
            && $user->hasPermission('workspace.buyer')
            && $siteVisit->status === SiteVisitStatus::Completed
            && $siteVisit->buyer_acknowledged_at === null;
    }
}
