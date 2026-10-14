<?php

namespace App\Services\Access;

use App\Models\Role;
use App\Models\User;
use InvalidArgumentException;

class RoleAssigner
{
    public function assign(User $user, string $role): void
    {
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

        $user->syncRoles($models);
    }
}
