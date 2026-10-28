<?php

namespace Tests\Feature\Subscription;

use App\Contracts\Payments\MpesaGateway;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\FakeMpesaGateway;
use App\Services\Payments\StkOutcome;
use App\Services\Subscription\Memberships;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorSubscriptionUxTest extends FoundationTestCase
{
    private FakeMpesaGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeMpesaGateway;
        $this->app->instance(MpesaGateway::class, $this->gateway);
        config(['mpesa.callback_secret' => 'callback-secret', 'mpesa.timeout_minutes' => 2, 'mpesa.driver' => 'daraja']);

        SubscriptionPlan::investorPass()->update([
            'checkout_amount' => 12900,
            'checkout_currency' => 'KES',
            'checkout_policy' => 'The KES amount is set by an administrator and is not an exchange-rate conversion.',
        ]);

        Http::preventStrayRequests();
    }

    public function test_active_subscriber_can_checkout_from_investor_workspace_without_buyer_permission(): void
    {
        $subscriber = User::factory()->withInvestorPass()->create();
        $subscriber->roles()->detach();
        $this->assertFalse($subscriber->fresh()->hasPermission('workspace.buyer'));

        $this->actingAs($subscriber)->get(route('investor.subscription.show'))
            ->assertOk()
            ->assertSee('Pay with M-Pesa', false)
            ->assertSee('Paid through', false);

        $this->actingAs($subscriber)->post(route('investor.subscription.checkout'), [
            'phone' => '254712345678',
            'idempotency_key' => 'investor-renew-1',
        ])->assertRedirect();

        $this->assertTrue($subscriber->fresh()->hasActiveInvestorPass());
        $payment = SubscriptionPayment::query()->where('user_id', $subscriber->id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    public function test_expired_member_renews_from_buyer_subscription_page(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance')->create();
        $memberships = app(Memberships::class);
        $subscription = $memberships->request($buyer);
        $memberships->activate($finance, $subscription);
        $subscription->update(['ends_at' => now()->subDay()]);
        $this->actingAs($buyer)->get(route('buyer.subscription.show'));

        $this->actingAs($buyer)->get('/investor')->assertForbidden();

        $this->actingAs($buyer)->get(route('buyer.subscription.show'))
            ->assertOk()
            ->assertSee('Expired', false)
            ->assertSee('Pay with M-Pesa', false);

        $this->actingAs($buyer)->post(route('buyer.subscription.checkout'), [
            'phone' => '254712345678',
            'idempotency_key' => 'expired-renew-1',
        ])->assertRedirect();

        $this->assertFalse($buyer->fresh()->hasActiveInvestorPass());
    }

    public function test_subscription_page_hides_checkout_when_kes_amount_is_not_configured(): void
    {
        SubscriptionPlan::investorPass()->update([
            'checkout_amount' => null,
            'checkout_currency' => null,
            'checkout_policy' => null,
            'currency' => 'USD',
        ]);
        config(['subscription.fx_to_kes.USD' => null]);

        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->get(route('buyer.subscription.show'))
            ->assertOk()
            ->assertSee('No payable KES amount has been set', false)
            ->assertDontSee('Pay with M-Pesa', false);
    }

    public function test_investor_checkout_does_not_activate_before_provider_confirmation(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($subscriber)->post(route('investor.subscription.checkout'), [
            'phone' => '254712345678',
            'idempotency_key' => 'investor-pay-1',
        ])->assertRedirect();

        $payment = SubscriptionPayment::query()->where('idempotency_key', 'investor-pay-1')->firstOrFail();
        $this->gateway->outcomes[$payment->checkout_request_id] = StkOutcome::paid('RCPT-INV-1', 12900);

        $this->withHeader('X-Callback-Secret', 'callback-secret')
            ->post(route('payments.mpesa.callback'), $this->payload($payment, 0, 'RCPT-INV-1'))
            ->assertNoContent();

        $this->assertTrue($subscriber->fresh()->hasActiveInvestorPass());
        $this->assertSame(SubscriptionStatus::Active, $payment->subscription->fresh()->status);
    }

    public function test_cancellation_copy_reflects_immediate_access_loss(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance')->create();
        $memberships = app(Memberships::class);
        $subscription = $memberships->request($buyer);
        $memberships->activate($finance, $subscription);

        $this->actingAs($buyer)->get(route('investor.subscription.show'))
            ->assertOk()
            ->assertSee('close immediately', false)
            ->assertSee('Cancel-at-period-end', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SubscriptionPayment $payment, int $code, ?string $receipt): array
    {
        $items = $receipt === null ? [] : [['Name' => 'MpesaReceiptNumber', 'Value' => $receipt]];

        return [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => $payment->checkout_request_id,
                    'ResultCode' => $code,
                    'CallbackMetadata' => ['Item' => $items],
                ],
            ],
        ];
    }
}
