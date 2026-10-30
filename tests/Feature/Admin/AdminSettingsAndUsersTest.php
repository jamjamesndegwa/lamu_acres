<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Foundation\FoundationTestCase;

class AdminSettingsAndUsersTest extends FoundationTestCase
{
    public function test_super_admin_can_open_settings_overview_permissions_and_roles(): void
    {
        $superAdmin = User::factory()->withRoles('super_admin')->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Permissions')
            ->assertSee('System operations');

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.permissions'))
            ->assertOk()
            ->assertSee('admin.users');

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.roles'))
            ->assertOk()
            ->assertSee('Super Administrator');
    }

    public function test_super_admin_can_reset_password_and_suspend_user_from_manage_screen(): void
    {
        $superAdmin = User::factory()->withRoles('super_admin')->create();
        $buyer = User::factory()->withRoles('buyer')->create([
            'email' => 'managed-buyer@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.users.show', $buyer))
            ->assertOk()
            ->assertSee('Reset password')
            ->assertSee('Suspend login');

        $this->actingAs($superAdmin)
            ->put(route('admin.users.password.update', $buyer), [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('admin.users.show', $buyer));

        $this->assertTrue(Hash::check('new-secure-password', $buyer->fresh()->password));

        $this->actingAs($superAdmin)
            ->put(route('admin.users.suspension.update', $buyer), [
                'action' => 'suspend',
                'suspension_reason' => 'Policy review',
            ])
            ->assertRedirect(route('admin.users.show', $buyer));

        $this->assertTrue($buyer->fresh()->isSuspended());
    }
}
