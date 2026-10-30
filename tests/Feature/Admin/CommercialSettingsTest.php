<?php

namespace Tests\Feature\Admin;

use App\Models\CommissionPolicy;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class CommercialSettingsTest extends FoundationTestCase
{
    public function test_commercial_settings_require_the_commercial_permission(): void
    {
        $staff = User::factory()->withRoles('staff')->create();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($staff)->get(route('admin.plans.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.commercial.commissions'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.commercial.listings'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.commercial.verification'))->assertForbidden();
        $this->actingAs($buyer)->put(route('admin.plans.update'), $this->planPayload())->assertForbidden();

        $this->assertSame(10000, SubscriptionPlan::investorPass()->amount_cents);
    }

    public function test_an_administrator_can_review_commercial_records_and_change_the_pass_price(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)->get(route('admin.plans.edit'))
            ->assertOk()
            ->assertSee('Investor Pass Plans')
            ->assertSee('Current price')
            ->assertDontSee('No commission agreement has been recorded.')
            ->assertDontSee('Publication status')
            ->assertDontSee('Checklist categories');

        $this->actingAs($admin)->get(route('admin.commercial.commissions'))
            ->assertOk()
            ->assertSee('No commission agreement has been recorded.')
            ->assertSee('Save commission policy')
            ->assertSee('Standard broker commission')
            ->assertSee('Open commission management')
            ->assertDontSee('Missing configuration: commission templates are not a managed contract.')
            ->assertDontSee('Current price')
            ->assertDontSee('Publication status');

        $this->actingAs($admin)->get(route('admin.commercial.listings'))
            ->assertOk()
            ->assertSee('Publication status')
            ->assertSee('Available')
            ->assertDontSee('Current price')
            ->assertDontSee('Checklist categories');

        $this->actingAs($admin)->get(route('admin.commercial.verification'))
            ->assertOk()
            ->assertSee('Verification Standards')
            ->assertSee('Checklist categories')
            ->assertSee('Missing configuration: verification checklist templates are not a managed contract.')
            ->assertDontSee('Current price')
            ->assertDontSee('Publication status');

        $this->actingAs($admin)->put(route('admin.plans.update'), $this->planPayload([
            'amount' => '250.00',
            'currency' => 'EUR',
            'interval' => 'month',
            'is_active' => '0',
        ]))->assertRedirect()->assertSessionHas('status', 'Investor Pass pricing updated.');

        $plan = SubscriptionPlan::investorPass()->fresh();
        $this->assertSame(25000, $plan->amount_cents);
        $this->assertSame('EUR', $plan->currency);
        $this->assertSame('month', $plan->interval);
        $this->assertFalse($plan->is_active);
    }

    public function test_an_invalid_price_is_rejected_and_the_stored_price_stays_unchanged(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)->from(route('admin.plans.edit'))
            ->put(route('admin.plans.update'), $this->planPayload(['amount' => '']))
            ->assertRedirect(route('admin.plans.edit'))
            ->assertSessionHasErrors('amount');

        $plan = SubscriptionPlan::investorPass()->fresh();
        $this->assertSame(10000, $plan->amount_cents);
        $this->assertSame('USD', $plan->currency);
        $this->assertTrue($plan->is_active);
    }

    public function test_an_administrator_can_update_the_commission_policy(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)->from(route('admin.commercial.commissions'))
            ->put(route('admin.commercial.commissions.update'), [
                'name' => 'Broker desk standard',
                'default_rate_percent' => '8.5',
                'default_fixed_amount' => '',
                'default_funding_party' => 'buyer',
                'default_payment_due_days' => '21',
                'default_protection_months' => '9',
                'terms' => 'Buyer-funded broker commission at eight and a half percent unless otherwise agreed in writing.',
                'apply_defaults' => '1',
            ])
            ->assertRedirect(route('admin.commercial.commissions'))
            ->assertSessionHas('status', 'Commission policy updated.');

        $policy = CommissionPolicy::standard()->fresh();
        $this->assertSame('Broker desk standard', $policy->name);
        $this->assertSame('8.50', $policy->default_rate_percent);
        $this->assertSame('buyer', $policy->default_funding_party);
        $this->assertSame(21, $policy->default_payment_due_days);
        $this->assertTrue($policy->apply_defaults);
    }

    public function test_a_super_administrator_can_open_the_operational_desks(): void
    {
        $super = User::factory()->withRoles('super_admin')->create();

        $this->actingAs($super)->get(route('admin.commercial.commissions'))
            ->assertOk()
            ->assertSee('Open commission management')
            ->assertDontSee('Open verification management');

        $this->actingAs($super)->get(route('admin.commercial.verification'))
            ->assertOk()
            ->assertSee('Open verification management')
            ->assertDontSee('Open commission management');
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Lamu Investor Pass',
            'amount' => '100.00',
            'currency' => 'USD',
            'interval' => 'year',
            'terms' => 'Research access for one billing period.',
            'is_active' => '1',
        ], $overrides);
    }
}
