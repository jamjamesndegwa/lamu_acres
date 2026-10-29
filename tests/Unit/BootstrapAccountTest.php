<?php

namespace Tests\Unit;

use App\Support\BootstrapAccount;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class BootstrapAccountTest extends TestCase
{
    public function test_production_requires_lamuacres_email_domain(): void
    {
        $this->app['env'] = 'production';
        Config::set('app.initial_admin.name', 'Admin');
        Config::set('app.initial_admin.email', 'admin@example.com');
        Config::set('app.initial_admin.password', 'password');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('@lamuacres.co.ke');

        BootstrapAccount::credentials(
            'app.initial_admin.name',
            'app.initial_admin.email',
            'app.initial_admin.password',
            'Initial administrator',
        );
    }

    public function test_production_accepts_valid_bootstrap_credentials(): void
    {
        $this->app['env'] = 'production';
        Config::set('app.initial_admin.name', 'Super Admin');
        Config::set('app.initial_admin.email', 'admin@lamuacres.co.ke');
        Config::set('app.initial_admin.password', 'password');

        $credentials = BootstrapAccount::credentials(
            'app.initial_admin.name',
            'app.initial_admin.email',
            'app.initial_admin.password',
            'Initial administrator',
        );

        $this->assertSame('admin@lamuacres.co.ke', $credentials['email']);
        $this->assertSame('password', $credentials['password']);
    }

    public function test_local_skips_empty_bootstrap_credentials(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.initial_admin.name', '');
        Config::set('app.initial_admin.email', '');
        Config::set('app.initial_admin.password', '');

        $credentials = BootstrapAccount::credentials(
            'app.initial_admin.name',
            'app.initial_admin.email',
            'app.initial_admin.password',
            'Initial administrator',
        );

        $this->assertSame('', $credentials['email']);
    }

    public function test_local_rejects_empty_password_when_credentials_are_set(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.initial_admin.name', 'Admin');
        Config::set('app.initial_admin.email', 'admin@lamuacres.test');
        Config::set('app.initial_admin.password', '');

        $this->expectException(InvalidArgumentException::class);

        BootstrapAccount::credentials(
            'app.initial_admin.name',
            'app.initial_admin.email',
            'app.initial_admin.password',
            'Initial administrator',
        );
    }
}
