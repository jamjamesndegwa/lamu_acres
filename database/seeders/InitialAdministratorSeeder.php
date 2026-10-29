<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Access\RoleAssigner;
use App\Support\BootstrapAccount;
use Illuminate\Database\Seeder;

class InitialAdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = BootstrapAccount::credentials(
            'app.initial_admin.name',
            'app.initial_admin.email',
            'app.initial_admin.password',
            'Initial administrator',
        );

        if ($credentials['email'] === '') {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => $credentials['name'],
                'password' => $credentials['password'],
                'email_verified_at' => now(),
            ],
        );

        app(RoleAssigner::class)->sync($user, ['super_admin']);
    }
}
