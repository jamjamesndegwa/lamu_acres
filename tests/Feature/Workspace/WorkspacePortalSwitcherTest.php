<?php

namespace Tests\Feature\Workspace;

use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class WorkspacePortalSwitcherTest extends FoundationTestCase
{
    public function test_investor_workspace_sidebar_offers_buyer_workspace_switch(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($subscriber)
            ->withSession(['workspace' => 'investor'])
            ->get(route('investor.dashboard'))
            ->assertOk()
            ->assertSee('Switch workspace', false)
            ->assertSee('Open Buyer workspace', false)
            ->assertSee(route('workspaces.switch', 'buyer'), false);
    }

    public function test_buyer_workspace_sidebar_offers_investor_workspace_switch(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($subscriber)
            ->withSession(['workspace' => 'buyer'])
            ->get(route('buyer.dashboard'))
            ->assertOk()
            ->assertSee('Open Investor workspace', false)
            ->assertSee(route('workspaces.switch', 'investor'), false);
    }

    public function test_public_account_menu_lists_accessible_portals(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($subscriber)->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Your portals', false)
            ->assertSee('Open Investor workspace', false)
            ->assertSee('Open Buyer workspace', false);
    }

    public function test_workspace_switch_route_opens_target_portal(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($subscriber)
            ->withSession(['workspace' => 'buyer'])
            ->post(route('workspaces.switch', 'investor'))
            ->assertRedirect(route('investor.dashboard'));

        $this->assertSame('investor', session('workspace'));
    }
}
