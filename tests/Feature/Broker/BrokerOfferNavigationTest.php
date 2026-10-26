<?php

namespace Tests\Feature\Broker;

use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class BrokerOfferNavigationTest extends FoundationTestCase
{
    public function test_authorized_broker_sees_submit_offer_navigation_and_can_open_form(): void
    {
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();

        $this->actingAs($broker)
            ->get(route('broker.dashboard'))
            ->assertOk()
            ->assertSee('Submit Offer for Buyer', false)
            ->assertSee('data-module="submit-offer"', false);

        $this->actingAs($broker)
            ->get(route('broker.offers.create'))
            ->assertOk()
            ->assertSee('Submit offer for buyer', false)
            ->assertSee(route('broker.offers.store'), false);
    }

    public function test_submit_offer_module_is_active_on_offer_create_route(): void
    {
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();

        $this->actingAs($broker)
            ->get(route('broker.offers.create'))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('Submit Offer for Buyer', false);
    }

    public function test_broker_without_verified_capability_cannot_access_offer_submission(): void
    {
        $broker = User::factory()->withRoles('broker')->create();

        $this->actingAs($broker)
            ->get(route('broker.offers.create'))
            ->assertForbidden();
    }

    public function test_suspended_broker_cannot_access_offer_submission(): void
    {
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create([
            'suspended_at' => now(),
        ]);

        $this->actingAs($broker)
            ->get(route('broker.offers.create'))
            ->assertRedirect();
    }
}
