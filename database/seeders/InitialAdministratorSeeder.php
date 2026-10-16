<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Access\RoleAssigner;
use Illuminate\Database\Seeder;
use InvalidArgumentException;
use RuntimeException;

class InitialAdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('app.initial_admin.name'));
        $email = strtolower(trim((string) config('app.initial_admin.email')));
        $password = (string) config('app.initial_admin.password');

        if ($name === '' && $email === '' && $password === '' && ! app()->environment('production')) {
            return;
        }

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 16) {
            if (app()->environment('production')) {
                throw new RuntimeException('Set INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL, and an INITIAL_ADMIN_PASSWORD of at least 16 characters before seeding production.');
            }

            throw new InvalidArgumentException('Set a valid initial administrator name, email, and password of at least 16 characters.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        app(RoleAssigner::class)->sync($user, ['super_admin']);
    }
}
