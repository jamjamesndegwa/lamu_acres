<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

class BootstrapAccount
{
    public const PRODUCTION_EMAIL_DOMAIN = '@lamuacres.co.ke';

    /**
     * @return array{name: string, email: string, password: string}
     */
    public static function credentials(string $nameKey, string $emailKey, string $passwordKey, string $label): array
    {
        $name = trim((string) config($nameKey));
        $email = strtolower(trim((string) config($emailKey)));
        $password = (string) config($passwordKey);

        if ($name === '' && $email === '' && $password === '' && ! app()->environment('production')) {
            return ['name' => '', 'email' => '', 'password' => ''];
        }

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException(sprintf(
                    'Set %s, %s, and %s before seeding production.',
                    self::envName($nameKey),
                    self::envName($emailKey),
                    self::envName($passwordKey),
                ));
            }

            throw new InvalidArgumentException(sprintf(
                'Set a valid %s name, email, and password.',
                strtolower($label),
            ));
        }

        if (app()->environment('production') && ! str_ends_with($email, self::PRODUCTION_EMAIL_DOMAIN)) {
            throw new RuntimeException(sprintf(
                '%s email must use %s in production.',
                $label,
                self::PRODUCTION_EMAIL_DOMAIN,
            ));
        }

        return compact('name', 'email', 'password');
    }

    private static function envName(string $configKey): string
    {
        return match ($configKey) {
            'app.initial_admin.name' => 'INITIAL_ADMIN_NAME',
            'app.initial_admin.email' => 'INITIAL_ADMIN_EMAIL',
            'app.initial_admin.password' => 'INITIAL_ADMIN_PASSWORD',
            'app.initial_staff.name' => 'INITIAL_STAFF_NAME',
            'app.initial_staff.email' => 'INITIAL_STAFF_EMAIL',
            'app.initial_staff.password' => 'INITIAL_STAFF_PASSWORD',
            default => strtoupper(str_replace('.', '_', $configKey)),
        };
    }
}
