<?php

namespace Tests\Feature\Subscription;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReceipt;
use App\Models\User;
use App\Services\Payments\FakeMpesaGateway;
use App\Services\Payments\StkOutcome;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Foundation\FoundationTestCase;

class MpesaCheckoutTest extends FoundationTestCase
{
    private FakeMpesaGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeMpesaGateway;
        $this->app->instance(\App\Contracts\Payments\MpesaGateway::class, $this->gateway);
        config(['mpesa.callback_secret' => 'callback-secret', 'mpesa.timeout_minutes' => 2]);

        SubscriptionPlan::investorPass()->update([
            'checkout_amount' => 12900,
            'checkout_currency' => 'KES',
            'checkout_policy' => 'The KES amount is set by an administrator and is not an exchange-rate conversion.',
        ]);

        Http::preventStrayRequests();
    }

    public function test_a_prompt_does_not_open_access_until_the_provider_result_matches(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $accounts = User::query()->count();

        $this->actingAs($buyer)->post(route('buyer.subscription.checkout'), [
            'phone' => '254712345678',
            'idempotency_key' => 'pay-1',
        ])->assertRedirect();

        $this->actingAs($buyer)->post(route('buyer.subscription.checkout'), [
            'phone' => '254712345678',
            'idempotency_key' => 'pay-1',
        ])->assertRedirect();

        $this->assertSame(1, $this->gateway->pushes);
        $this->assertSame($accounts, User::query()->count());
        $this->assertFalse($buyer->fresh()->hasActiveInvestorPass());

        $payment = SubscriptionPayment::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(12900, $payment->amount);
        $this->assertSame('KES', $payment->currency);
        $this->assertNotSame(SubscriptionPlan::investorPass()->amount_cents, $payment->amount);
        $this->assertStringContainsString('not an exchange-rate conversion', $payment->invoice->policy);

        $this->post(route('payments.mpesa.callback'), $this->payload($payment, 0, 'RCPT100'))->assertForbidden();

        $this->withHeader('X-Callback-Secret', 'callback-secret')
            ->post(route('payments.mpesa.callback'), $this->payload($payment, 0, 'RCPT100'))
            ->assertNoContent();

        $this->assertFalse($buyer->fresh()->hasActiveInvestorPass());
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $this->gateway->outcomes[$payment->checkout_request_id] = StkOutcome::paid('RCPT100', 12900);

        $ends = null;
        $this->withHeader('X-Callback-Secret', 'callback-secret')
            ->post(route('payments.mpesa.callback'), $this->payload($payment, 0, 'RCPT100'))
            ->assertNoContent();

        $buyer->refresh();
        $this->assertTrue($buyer->hasActiveInvestorPass());
        $this->assertSame(SubscriptionStatus::Active, $payment->subscription->fresh()->status);
        $this->assertSame(1, SubscriptionReceipt::query()->count());
        $ends = $payment->subscription->fresh()->ends_at;

        $this->withHeader('X-Callback-Secret', 'callback-secret')
            ->post(route('payments.mpesa.callback'), $this->payload($payment, 0, 'RCPT100'))
            ->assertNoContent();

        $this->assertSame(1, SubscriptionReceipt::query()->count());
        $this->assertTrue($ends->equalTo($payment->subscription->fresh()->ends_at));
        $this->assertSame(1, $payment->subscription->events()->where('action', 'activated')->count());
    }

    public function test_failure_and_timeout_leave_access_closed_and_can_be_retried(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('buyer.subscription.checkout'), [
            'phone' => '254712345678',
            'idempotency_key' => 'fail-1',
        ])->assertRedirect();

        $payment = SubscriptionPayment::query()->firstOrFail();
        $this->gateway->outcomes[$payment->checkout_request_id] = StkOutcome::failed(1032, 'Cancelled on the handset.');

        $this->withHeader('X-Callback-Secret', 'callback-secret')
            ->post(route('payments.mpesa.callback'), $this->payload($payment, 1032, null))
            ->assertNoContent();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertFalse($buyer->fresh()->hasActiveInvestorPass());

        $second = User::factory()->withRoles('buyer')->create();
        $this->actingAs($second)->post(route('buyer.subscription.checkout'), [
            'phone' => '254711111111',
            'idempotency_key' => 'timeout-1',
        ])->assertRedirect();

        $timed = SubscriptionPayment::query()->where('user_id', $second->id)->firstOrFail();
        $timed->update(['expires_at' => now()->subMinute()]);
        $this->gateway->throwOnQuery = true;

        $this->artisan('mpesa:reconcile')->assertSuccessful();

        $this->assertSame(PaymentStatus::TimedOut, $timed->fresh()->status);
        $this->assertFalse($second->fresh()->hasActiveInvestorPass());

        $this->gateway->throwOnQuery = false;
        $this->actingAs($second)->post(route('buyer.subscription.retry', $timed), [
            'idempotency_key' => 'timeout-2',
        ])->assertRedirect();

        $this->assertSame(3, $this->gateway->pushes);
        $this->assertFalse($second->fresh()->hasActiveInvestorPass());
        $this->assertSame(PaymentStatus::Pending, SubscriptionPayment::query()->where('idempotency_key', 'timeout-2')->first()->status);
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
