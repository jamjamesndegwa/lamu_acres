<?php

namespace App\Services\Communications;

use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;

class PropertyInterestRecipients
{
    /**
     * @return Collection<int, User>
     */
    public function verifiedSellers(Property $property): Collection
    {
        $property->loadMissing('owners.user');

        $sellers = $property->owners
            ->where('status', OwnerLinkStatus::Verified)
            ->whereIn('relationship', OwnerRelationship::sellerFacing())
            ->map(fn ($owner) => $owner->user)
            ->filter();

        if ($sellers->isEmpty() && $property->submitted_by !== null) {
            $submitter = User::query()->find($property->submitted_by);

            if ($submitter !== null && $submitter->canUseSellerFeatures()) {
                $sellers = collect([$submitter]);
            }
        }

        return $sellers->unique('id')->values();
    }

    public function listingBroker(Property $property): ?User
    {
        if ($property->submittedBySeller()) {
            return null;
        }

        if ($property->submitted_by === null) {
            return null;
        }

        $broker = User::query()->find($property->submitted_by);

        if ($broker !== null && $broker->canUseBrokerFeatures()) {
            return $broker;
        }

        return null;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    public function dedupeUsers(Collection $users): Collection
    {
        return $users->filter()->unique('id')->values();
    }
}
