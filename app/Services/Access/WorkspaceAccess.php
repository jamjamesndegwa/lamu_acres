<?php

namespace App\Services\Access;

use App\Enums\PartnerCapability;
use App\Enums\Workspace;
use App\Models\User;

class WorkspaceAccess
{
    public function __construct(private CapabilityReviewer $capabilities) {}

    public function canAccess(User $user, Workspace $workspace): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return match ($workspace) {
            Workspace::Investor => $user->hasActiveInvestorPass(),
            Workspace::Buyer => $this->canUseBuyerFeatures($user),
            Workspace::Seller => $this->canUseSellerFeatures($user),
            Workspace::Broker => $user->hasPermission('workspace.broker'),
            Workspace::Staff => $user->hasPermission('workspace.staff'),
            Workspace::Admin => $user->hasPermission('workspace.admin'),
        };
    }

    public function canUseBuyerFeatures(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        if ($user->hasPermission('workspace.buyer')) {
            return true;
        }

        return $this->hasVerifiedBroker($user);
    }

    public function canUseSellerFeatures(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        if ($user->hasPermission('workspace.seller')) {
            if ($this->sellerCapabilityIsEnforced($user)) {
                return $this->hasVerifiedSeller($user);
            }

            return true;
        }

        return $this->hasVerifiedBroker($user);
    }

    public function canUseBrokerFeatures(User $user): bool
    {
        if ($user->isSuspended() || ! $user->hasPermission('workspace.broker')) {
            return false;
        }

        return $this->hasVerifiedBroker($user);
    }

    public function hasVerifiedBroker(User $user): bool
    {
        return $user->hasPermission('workspace.broker')
            && $this->capabilities->hasCurrentCapability($user, PartnerCapability::Broker);
    }

    public function hasVerifiedSeller(User $user): bool
    {
        return $user->hasPermission('workspace.seller')
            && $this->capabilities->hasCurrentCapability($user, PartnerCapability::Seller);
    }

    public function sellerCapabilityIsEnforced(User $user): bool
    {
        return $user->capabilityApplications()
            ->where('capability', PartnerCapability::Seller)
            ->exists();
    }
}
