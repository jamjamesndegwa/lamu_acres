<?php

namespace Database\Seeders;

use App\Models\Entitlement;
use App\Models\User;
use App\Services\Access\RoleAssigner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessSeeder::class,
            GeographySeeder::class,
        ]);

        if (! app()->environment('local')) {
            return;
        }

        $accounts = [
            ['name' => 'Buyer Account', 'email' => 'buyer@lamuacres.test', 'roles' => ['buyer']],
            ['name' => 'Broker Account', 'email' => 'broker@lamuacres.test', 'roles' => ['broker']],
            ['name' => 'Landowner Account', 'email' => 'seller@lamuacres.test', 'roles' => ['seller']],
            ['name' => 'Investor Account', 'email' => 'investor@lamuacres.test', 'roles' => ['buyer'], 'investor_pass' => true],
            ['name' => 'Staff Account', 'email' => 'staff@lamuacres.test', 'roles' => ['staff'], 'permissions' => [
                'staff.overview',
                'staff.properties',
                'staff.buyers',
                'staff.sourcing',
                'staff.brokerage',
                'staff.sales',
                'staff.verification',
                'staff.intelligence',
                'staff.finance',
                'staff.communications',
            ]],
            ['name' => 'Verification Officer', 'email' => 'verification@lamuacres.test', 'roles' => ['staff'], 'permissions' => ['staff.verification']],
            ['name' => 'Finance Officer', 'email' => 'finance@lamuacres.test', 'roles' => ['staff'], 'permissions' => ['staff.finance', 'commissions.propose', 'commissions.approve', 'commissions.record']],
            ['name' => 'Administrator', 'email' => 'administrator@lamuacres.test', 'roles' => ['admin']],
            ['name' => 'Super Administrator', 'email' => 'admin@lamuacres.test', 'roles' => ['super_admin']],
            ['name' => 'Multi Workspace', 'email' => 'multi@lamuacres.test', 'roles' => ['buyer', 'broker']],
        ];

        $roles = app(RoleAssigner::class);

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );

            $roles->sync($user, $account['roles']);

            if ($account['permissions'] ?? []) {
                $user->syncPermissions($account['permissions']);
            }

            if ($account['investor_pass'] ?? false) {
                $user->entitlements()->updateOrCreate(
                    ['code' => Entitlement::INVESTOR_PASS],
                    [
                        'status' => Entitlement::STATUS_ACTIVE,
                        'starts_at' => now(),
                        'ends_at' => null,
                    ],
                );
            }
        }
    }
}
