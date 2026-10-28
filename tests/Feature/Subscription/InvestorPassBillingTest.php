<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscription\InvestorPassBilling;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorPassBillingTest extends FoundationTestCase
{
    public function test_usd_list_price_converts_to_kes_at_checkout_when_no_manual_amount(): void
    {
        config(['subscription.fx_to_kes.USD' => 130]);

        $plan = SubscriptionPlan::investorPass();
        $plan->update([
            'amount_cents' => 10000,
            'currency' => 'USD',
            'checkout_amount' => null,
            'checkout_currency' => null,
            'checkout_policy' => null,
            'is_active' => true,
        ]);

        $billing = app(InvestorPassBilling::class);

        $this->assertSame(13000, $billing->mpesaAmountKes($plan->fresh()));
        $this->assertTrue($billing->mpesaCheckoutReady($plan->fresh()));
        $this->assertStringContainsString('130.00 KES per USD', (string) $billing->conversionSummary($plan->fresh()));
    }

    public function test_manual_checkout_amount_overrides_fx_conversion(): void
    {
        config(['subscription.fx_to_kes.USD' => 200]);

        $plan = SubscriptionPlan::investorPass();
        $plan->update([
            'amount_cents' => 10000,
            'currency' => 'USD',
            'checkout_amount' => 12900,
            'checkout_currency' => 'KES',
            'checkout_policy' => 'Fixed KES checkout for demos.',
        ]);

        $billing = app(InvestorPassBilling::class);

        $this->assertSame(12900, $billing->mpesaAmountKes($plan->fresh()));
        $this->assertNull($billing->conversionSummary($plan->fresh()));
    }

    public function test_seller_workspace_can_open_investor_pass_subscription_page(): void
    {
        $seller = User::factory()->withRoles('seller')->create();

        $this->actingAs($seller)->get(route('seller.subscription.show'))
            ->assertOk()
            ->assertSee('Billing method', false)
            ->assertSee('M-Pesa STK Push', false);
    }

    public function test_seller_can_request_membership_from_seller_subscription_route(): void
    {
        $seller = User::factory()->withRoles('seller')->create();

        $this->actingAs($seller)->post(route('seller.subscription.store'))->assertRedirect();
        $this->actingAs($seller)->post(route('buyer.subscription.store'))->assertForbidden();
    }
}
