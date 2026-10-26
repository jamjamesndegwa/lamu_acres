<?php

namespace Tests\Feature\Foundation;

use App\Livewire\WorkspaceSubnav;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

class WorkspaceFoundationTest extends FoundationTestCase
{
    public function test_buyer_land_directory_keeps_the_public_hero_inside_the_workspace(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->get('/land')->assertOk()
            ->assertSee('Explore Land in Lamu')
            ->assertSee('images/explore_land_hero_section.jpg', false)
            ->assertSee('id="workspace-sidebar"', false)
            ->assertSee('Interactive area overview')
            ->assertSee('No land has been published yet.');
    }

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

    public function test_new_buyer_can_register_log_out_and_sign_in_again(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Create a buyer account')->assertSee('Welcome back');
        $this->get(route('register'))->assertOk()->assertSee('Create buyer account');

        $this->post(route('register'), [
            'name' => 'Coastal Buyer', 'email' => 'coastal-buyer@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect(route('buyer.dashboard'));
        $buyer = User::query()->where('email', 'coastal-buyer@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($buyer);
        $this->assertTrue($buyer->hasRole('buyer'));
        $this->get(route('buyer.dashboard'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
        $this->post(route('login'), [
            'email' => 'coastal-buyer@example.com', 'password' => 'password',
        ])->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($buyer);
        $this->get(route('buyer.dashboard'))->assertOk();
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

        $buyerPage = $this->actingAs($user)->get('/buyer')->assertOk()->assertSee('Switch Workspace');
        $this->assertMatchesRegularExpression('/id="switch-workspace"[^>]*data-turbo-frame="modal"/', $buyerPage->getContent());

        $this->actingAs($user)
            ->withHeader('Turbo-Frame', 'modal')
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertSee('Choose a workspace')
            ->assertSee('Buyer')
            ->assertSee('Broker Partner')
            ->assertSee('id="modal"', false)
            ->assertDontSee('<!DOCTYPE html>', false);

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
            ->assertSee('Calendar')
            ->assertSee('Tasks')
            ->assertDontSee('data-module="verification"', false)
            ->assertDontSee('Review Queue')
            ->assertDontSee('data-module="finance"', false)
            ->assertDontSee('Settlements');

        $this->assertFalse($staff->hasPermission('staff.verification'));
        $this->assertFalse($staff->hasPermission('commissions.approve'));

        $this->actingAs($verification)
            ->get('/staff')
            ->assertOk()
            ->assertSee('data-module="verification"', false)
            ->assertSee('Calendar')
            ->assertSee('Review Queue')
            ->assertSee('Evidence')
            ->assertSee('Exceptions')
            ->assertDontSee('data-module="finance"', false)
            ->assertDontSee('Settlements');

        $this->actingAs($verification)->get('/admin')->assertForbidden();

        $this->actingAs($finance)
            ->get('/staff')
            ->assertOk()
            ->assertSee('data-module="finance"', false)
            ->assertSee('Subscriptions')
            ->assertSee('Commissions')
            ->assertSee('Settlements')
            ->assertDontSee('Review Queue');
        $this->assertTrue($finance->hasPermission('commissions.approve'));

        $operations = User::factory()->withRoles('staff')->withPermissions(
            'staff.properties',
            'staff.buyers',
            'staff.sourcing',
            'staff.brokerage',
            'staff.sales',
            'staff.verification',
            'staff.intelligence',
            'staff.finance',
            'staff.communications',
        )->create();

        $this->actingAs($operations)
            ->get('/staff')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Tasks')
            ->assertSee('Calendar')
            ->assertSee('Property Register')
            ->assertSee('Listing Reviews')
            ->assertSee('Property Media')
            ->assertSee('Buyers & CRM')
            ->assertSee('Enquiries')
            ->assertSee('Viewings')
            ->assertSee('Document Requests')
            ->assertSee('Requirements')
            ->assertSee('Matching')
            ->assertSee('Approvals')
            ->assertSee('Applications')
            ->assertSee('Referrals')
            ->assertSee('Seller Authority')
            ->assertSee('Pipeline')
            ->assertSee('Offers')
            ->assertSee('Completion')
            ->assertSee('Review Queue')
            ->assertSee('Evidence')
            ->assertSee('Exceptions')
            ->assertSee('Projects')
            ->assertSee('Imagery')
            ->assertSee('Planning')
            ->assertSee('Research')
            ->assertSee('Subscriptions')
            ->assertSee('Commissions')
            ->assertSee('Settlements')
            ->assertSee('Messages and work updates');

        $this->actingAs($operations)->get(route('staff.tasks'))->assertOk()->assertSee('Tasks');
        $this->actingAs($operations)->get(route('staff.calendar'))->assertOk()->assertSee('Calendar');
        $this->actingAs($operations)->get(route('staff.media.index'))->assertOk()->assertSee('Property media');
        $this->actingAs($operations)->get(route('staff.authorities.index'))->assertOk()->assertSee('Seller authority');
        $this->actingAs($operations)->get(route('staff.sourcing.matching'))->assertOk()->assertSee('Matching');
        $this->actingAs($operations)->get(route('staff.sourcing.approvals'))->assertOk()->assertSee('Approvals');

        session([
            'workspace' => 'staff',
            'workspace_route' => 'staff.verification.index',
        ]);

        Livewire::actingAs($verification)
            ->test(WorkspaceSubnav::class)
            ->assertSee('Review Queue')
            ->assertSee('Evidence')
            ->assertSee('Exceptions')
            ->assertDontSee('Settlements');

        session([
            'workspace' => 'staff',
            'workspace_route' => 'staff.memberships.index',
        ]);

        Livewire::actingAs($finance)
            ->test(WorkspaceSubnav::class)
            ->assertSee('Subscriptions')
            ->assertSee('Commissions')
            ->assertSee('Settlements')
            ->assertDontSee('Review Queue');
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
            ->assertDontSee('Staff & administration');

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Partner accounts')
            ->assertSee('Staff & administration')
            ->assertSee('Access History')
            ->assertDontSee('Sales Performance');

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('data-module="users"', false)
            ->assertDontSee('Partner accounts');
    }

    public function test_each_workspace_keeps_submodules_in_the_horizontal_bar(): void
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();

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
            'workspace_route' => 'seller.properties.index',
        ]);

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
            ->assertSee('My Offers')
            ->assertSee('Explore Land')
            ->assertSee('Saved Properties')
            ->assertSee('Find Land for Me')
            ->assertSee('My Property Matches')
            ->assertSee('Buying Profile')
            ->assertSee('Investor Pass')
            ->assertSee('data-module="enquiries"', false);

        $broker = User::factory()->withRoles('broker')->create();

        $this->actingAs($broker)
            ->get('/broker')
            ->assertOk()
            ->assertSee('data-module="listings"', false)
            ->assertSee('data-module="referrals"', false)
            ->assertSee('data-module="submit-offer"', false)
            ->assertSee('data-module="deals"', false)
            ->assertSee('Submit Property')
            ->assertSee('Buyer Requirements Board')
            ->assertSee('Commission Agreements')
            ->assertSee('Earnings & Payments')
            ->assertSee('Profile');

        $investor = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($investor)
            ->get('/investor')
            ->assertOk()
            ->assertSee('data-module="tracker"', false)
            ->assertSee('data-module="imagery"', false)
            ->assertSee('data-module="watchlists"', false)
            ->assertSee('data-module="subscription"', false)
            ->assertSee('Satellite & Aerial Imagery')
            ->assertDontSee('Satellite Explorer');
    }

    public function test_investor_workspace_opens_every_intelligence_screen(): void
    {
        $investor = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($investor)
            ->get('/investor')
            ->assertOk()
            ->assertSee('Intelligence Dashboard')
            ->assertSee('Development Tracker')
            ->assertSee('Planning & Zoning')
            ->assertSee('Land Price Observatory')
            ->assertSee('Research Reports')
            ->assertSee('My Watchlists')
            ->assertSee('Alerts & Archive')
            ->assertSee('Subscription')
            ->assertSee('Profile')
            ->assertSee('Logout')
            ->assertSee('Membership')
            ->assertSee('Latest research')
            ->assertSee('Watched projects');

        $this->actingAs($investor)->get(route('investor.projects.index'))->assertOk()->assertSee('Development Tracker')->assertSee('Status')->assertSee('Type')->assertSee('Location');
        $this->actingAs($investor)->get(route('investor.imagery.index'))->assertOk()->assertSee('Satellite & Aerial Imagery');
        $this->actingAs($investor)->get(route('investor.planning.index'))->assertOk()->assertSee('Planning & Zoning');
        $this->actingAs($investor)->get(route('investor.prices'))->assertOk()->assertSee('Land Price Observatory')->assertSee('Asking prices');
        $this->actingAs($investor)->get(route('investor.reports.index'))->assertOk()->assertSee('Research Reports')->assertSee('No research report has been published');
        $this->actingAs($investor)->get(route('investor.watchlists.index'))->assertOk()->assertSee('My Watchlists')->assertSee('Watched locations')->assertSee('Watched projects');
        $this->actingAs($investor)->get(route('investor.archive.index'))->assertOk()->assertSee('Alerts & Archive')->assertSee('Published alerts');
        $this->actingAs($investor)->get(route('investor.subscription.show'))->assertOk()->assertSee('Active entitlement')->assertSee('Renewal')->assertSee('Payment history');
        $this->actingAs($investor)->get(route('investor.profile'))->assertOk()->assertSee('Profile & Security')->assertSee('Name')->assertSee('Email');

        auth()->logout();

        $this->get(route('intelligence.projects.index'))
            ->assertOk()
            ->assertSee('Development Tracker')
            ->assertSee('Satellite & Aerial Imagery')
            ->assertSee('Subscribe');
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
