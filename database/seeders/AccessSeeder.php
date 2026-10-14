<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (config('access.permissions') as $name => $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $permission['label'], 'group' => $permission['group']],
            );
        }

        foreach (config('access.roles') as $name => $role) {
            $model = Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'label' => $role['label'],
                    'workspace' => $role['workspace'],
                    'description' => $role['description'],
                ],
            );

            $model->syncPermissions($role['permissions']);
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', array_keys(config('access.permissions')))
            ->delete();

        Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', array_keys(config('access.roles')))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
