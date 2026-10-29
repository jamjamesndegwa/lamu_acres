<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Access\RoleAssigner;
use App\Support\BootstrapAccount;
use Illuminate\Database\Seeder;
use RuntimeException;

class InitialStaffSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const STAFF_PERMISSIONS = [
        'staff.overview',
        'staff.properties',
        'staff.buyers',
        'staff.sourcing',
        'staff.brokerage',
        'staff.sales',
        'staff.verification',
        'staff.intelligence',
        'intelligence.publish',
        'staff.finance',
        'staff.communications',
    ];

    public function run(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $credentials = BootstrapAccount::credentials(
            'app.initial_staff.name',
            'app.initial_staff.email',
            'app.initial_staff.password',
            'Initial staff account',
        );

        if ($credentials['email'] === '') {
            throw new RuntimeException('Set INITIAL_STAFF_NAME, INITIAL_STAFF_EMAIL, and INITIAL_STAFF_PASSWORD before seeding production.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => $credentials['name'],
                'password' => $credentials['password'],
                'email_verified_at' => now(),
            ],
        );

        app(RoleAssigner::class)->sync($user, ['staff']);
        $user->syncPermissions(self::STAFF_PERMISSIONS);
    }
}
