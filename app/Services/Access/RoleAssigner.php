<?php

namespace App\Services\Access;

use App\Enums\PartnerCapability;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RoleAssigner
{
    public function assign(User $user, string $role): void
    {
        $this->assertCapabilityGrant($user, $role);
        $user->assignRole($role);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function sync(User $user, array $roles): void
    {
        $roles = array_values(array_unique($roles));

        $models = Role::query()->where('guard_name', 'web')->whereIn('name', $roles)->get();

        if ($models->count() !== count($roles)) {
            throw new InvalidArgumentException('One or more roles are unknown.');
        }

        foreach ($roles as $role) {
            $this->assertCapabilityGrant($user, $role);
        }
        $user->syncRoles($models);
    }

    private function assertCapabilityGrant(User $user, string $role): void
    {
        $capability = PartnerCapability::tryFrom($role);
        if ($capability === null) {
            return;
        }
        if (! app(CapabilityReviewer::class)->hasCurrentCapability($user, $capability)) {
            throw ValidationException::withMessages(['roles' => 'Seller and broker capabilities require approval through the verified capability application.']);
        }
    }
}
