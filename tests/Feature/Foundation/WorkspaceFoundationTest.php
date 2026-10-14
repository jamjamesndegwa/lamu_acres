<?php

namespace Tests\Feature\Foundation;

use App\Livewire\WorkspaceSidebar;
use App\Livewire\WorkspaceSubnav;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

class WorkspaceFoundationTest extends FoundationTestCase
{
    public function test_public_pages_stay_open_and_private_routes_ask_guests_to_login(): void
    {
        $this->get('/')->assertOk()->assertSee('Lamu Acres')->assertSee('Explore Land');
        $this->get('/land')->assertOk()->assertSee('Land in Lamu');
        $this->get('/buyer')->assertRedirect(route('login'));
    }

    public function test_login_returns_the_visitor_to_the_workspace_they_asked_for(): void
    {
        User::factory()->withRoles('broker')->create([
            'email' => 'broker@example.com',
        ]);

        $this->get('/login');
        $sessionId = session()->getId();
        $token = session()->token();

        $this->post('/login', [
            'email' => 'broker@example.com',
            'password' => 'password',
        ])->assertRedirect('/broker');

        $this->assertAuthenticated();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
    }

    public function test_registration_creates_a_buyer_and_ignores_a_posted_role(): void
    {
        $this->post('/register', [
            'name' => 'Amina Hassan',
            'email' => 'amina@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'super_admin',
        ])->assertRedirect('/buyer');

        $user = User::query()->where('email', 'amina@example.com')->first();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('buyer'));
        $this->assertFalse($user->hasRole('super_admin'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_a_buyer_can_open_only_the_buyer_workspace(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $response = $this->actingAs($buyer)->get('/buyer');

        $response->assertOk()->assertDontSee('Switch Workspace');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);

        $this->actingAs($buyer)->get('/staff')->assertForbidden();
    }

    public function test_a_person_with_two_workspaces_can_switch_and_cannot_open_a_third(): void
    {
        $user = User::factory()->withRoles('buyer', 'broker')->create();

        $this->actingAs($user)->get('/buyer')->assertOk()->assertSee('Switch Workspace');

        $this->actingAs($user)
            ->post(route('workspaces.switch', 'broker'))
            ->assertRedirect(route('broker.dashboard'));

        $this->actingAs($user)->get('/broker')->assertOk();
        $this->actingAs($user)->post(route('workspaces.switch', 'staff'))->assertForbidden();
        $this->actingAs($user)->get('/investor')->assertForbidden();
    }

    public function test_investor_access_follows_an_active_pass_and_not_a_role(): void
    {
        $this->assertFalse(Role::query()->where('name', 'investor')->exists());

        $buyer = User::factory()->withRoles('buyer')->create();
        $this->actingAs($buyer)->get('/investor')->assertForbidden();

        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($subscriber)
            ->get('/investor')
            ->assertOk()
            ->assertSee('verified subscription payment')
            ->assertSee('does not release private property records');

        $this->actingAs($subscriber)->get('/buyer')->assertOk()->assertSee('Switch Workspace');

        $expired = User::factory()->withRoles('buyer')->withInvestorPass(now()->subDay())->create();
        $this->actingAs($expired)->get('/investor')->assertForbidden();
    }

    public function test_logout_ends_the_session(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer);
        $sessionId = session()->getId();
        $token = session()->token();

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->get('/buyer')->assertRedirect(route('login'));
        $this->get('/')->assertOk();
    }

    public function test_staff_sidebars_follow_the_assigned_desk(): void
    {
        $staff = User::factory()->withRoles('staff')->create();
        $verification = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance', 'commissions.approve', 'commissions.record')->create();

        $this->actingAs($staff)
            ->get('/staff')
            ->assertOk()
            ->assertSee('Calendar & Follow-ups')
            ->assertDontSee('data-module="verification"', false)
            ->assertDontSee('Verification Queue')
            ->assertDontSee('data-module="finance"', false)
            ->assertDontSee('Commission Payables');

        $this->assertFalse($staff->hasPermission('staff.verification'));
        $this->assertFalse($staff->hasPermission('commissions.approve'));

        $this->actingAs($verification)
            ->get('/staff')
            ->assertOk()
            ->assertSee('data-module="verification"', false)
            ->assertSee('Calendar & Follow-ups')
            ->assertDontSee('Verification Queue')
            ->assertDontSee('data-module="finance"', false)
            ->assertDontSee('Commission Payables');

        $this->actingAs($verification)->get('/admin')->assertForbidden();

        $this->actingAs($finance)
            ->get('/staff')
            ->assertOk()
            ->assertSee('data-module="finance"', false)
            ->assertDontSee('Commission Payables');
        $this->assertTrue($finance->hasPermission('commissions.approve'));

        session([
            'workspace' => 'staff',
            'workspace_route' => 'staff.dashboard',
        ]);

        Livewire::actingAs($verification)
            ->test(WorkspaceSidebar::class)
            ->call('select', 'verification');

        Livewire::actingAs($verification)
            ->test(WorkspaceSubnav::class)
            ->assertSee('Verification Queue')
            ->assertSee('Document Reviews')
            ->assertDontSee('Commission Payables');

        session([
            'workspace' => 'staff',
            'workspace_route' => 'staff.dashboard',
        ]);

        Livewire::actingAs($finance)
            ->test(WorkspaceSidebar::class)
            ->call('select', 'finance');

        Livewire::actingAs($finance)
            ->test(WorkspaceSubnav::class)
            ->assertSee('Commission Payables')
            ->assertDontSee('Verification Queue');
    }

    public function test_financial_authority_is_separate_from_administration(): void
    {
        $superAdmin = User::factory()->withRoles('super_admin')->create();
        $administrator = User::factory()->withRoles('admin')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();

        $this->assertFalse($superAdmin->hasPermission('commissions.approve'));
        $this->assertTrue($superAdmin->hasPermission('admin.users'));
        $this->assertTrue($superAdmin->hasPermission('intelligence.publish'));
        $this->assertFalse($administrator->hasPermission('admin.users'));
        $this->assertTrue($editor->hasPermission('intelligence.publish'));
        $this->assertFalse($researcher->hasPermission('intelligence.publish'));

        $this->actingAs($superAdmin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-module="users"', false)
            ->assertDontSee('User Directory');

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('User Directory')
            ->assertSee('Access History')
            ->assertDontSee('Sales Performance');

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('data-module="users"', false)
            ->assertDontSee('User Directory');
    }

    public function test_each_workspace_keeps_submodules_in_the_horizontal_bar(): void
    {
        $seller = User::factory()->withRoles('seller')->create();

        $this->actingAs($seller)
            ->get('/seller')
            ->assertOk()
            ->assertSee('id="workspace-subnav"', false)
            ->assertSee('data-module="properties"', false)
            ->assertSee('data-module="sales"', false)
            ->assertDontSee('List Property')
            ->assertDontSee('Transactions');

        session([
            'workspace' => 'seller',
            'workspace_route' => 'seller.dashboard',
        ]);

        Livewire::actingAs($seller)
            ->test(WorkspaceSidebar::class)
            ->call('select', 'properties');

        Livewire::actingAs($seller)
            ->test(WorkspaceSubnav::class)
            ->assertSee('My Properties')
            ->assertSee('List Property')
            ->assertSee('Documents')
            ->assertDontSee('Transactions');

        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)
            ->get(route('buyer.enquiries.index'))
            ->assertOk()
            ->assertSee('Site Visits')
            ->assertSee('Offers')
            ->assertSee('data-module="properties"', false)
            ->assertDontSee('Saved Properties')
            ->assertDontSee('Find Land for Me');

        $broker = User::factory()->withRoles('broker')->create();

        $this->actingAs($broker)
            ->get('/broker')
            ->assertOk()
            ->assertSee('data-module="listings"', false)
            ->assertSee('data-module="referrals"', false)
            ->assertSee('data-module="deals"', false)
            ->assertDontSee('Submit Property');

        $investor = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($investor)
            ->get('/investor')
            ->assertOk()
            ->assertSee('data-module="intelligence"', false)
            ->assertSee('data-module="watchlists"', false)
            ->assertSee('data-module="subscription"', false)
            ->assertDontSee('Satellite Explorer');
    }

    public function test_only_an_administrator_can_change_roles_and_cannot_remove_their_own(): void
    {
        $admin = User::factory()->withRoles('super_admin')->create();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($admin)
            ->put(route('admin.users.roles.update', $buyer), [
                'roles' => ['buyer', 'broker'],
            ])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertTrue($buyer->hasRole('buyer'));
        $this->assertTrue($buyer->hasRole('broker'));

        $this->actingAs($buyer)
            ->put(route('admin.users.roles.update', $buyer), [
                'roles' => ['super_admin'],
            ])
            ->assertForbidden();

        $this->assertFalse($buyer->fresh()->hasRole('super_admin'));

        $this->actingAs($admin)
            ->put(route('admin.users.roles.update', $admin), [
                'roles' => ['buyer', 'super_admin'],
            ])
            ->assertForbidden();

        $admin->refresh();
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertFalse($admin->hasRole('buyer'));
    }

    public function test_phone_sign_in_does_not_pretend_a_code_was_delivered(): void
    {
        $this->post(route('login.phone'), [
            'phone' => '+254700000000',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertDatabaseCount('otp_challenges', 0);

        $this->post(route('login.phone.verify'), [
            'phone' => '+254700000000',
            'code' => '000000',
        ])->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertSame(0, OtpChallenge::query()->count());
    }

    public function test_an_account_with_no_role_is_told_that_no_workspace_is_assigned(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/buyer')->assertForbidden();

        $this->actingAs($user)
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertSee('No workspace has been assigned to this account.');
    }
}
