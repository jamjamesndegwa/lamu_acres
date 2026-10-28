<?php

namespace Tests\Feature\Subscription;

use App\Contracts\Payments\MpesaGateway;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\DarajaMpesaGateway;
use App\Services\Payments\FakeMpesaGateway;
use App\Services\Payments\MpesaPhone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class DarajaMpesaGatewayTest extends FoundationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mpesa.driver' => 'daraja',
            'mpesa.environment' => 'sandbox',
            'mpesa.base_url' => null,
            'mpesa.consumer_key' => 'consumer-key',
            'mpesa.consumer_secret' => 'consumer-secret',
            'mpesa.passkey' => 'pass-key',
            'mpesa.shortcode' => '174379',
            'mpesa.callback_url' => 'https://app.example.test/payments/mpesa/callback/callback-token',
            'mpesa.callback_secret' => 'callback-token',
            'mpesa.transaction_type' => 'CustomerPayBillOnline',
        ]);

        SubscriptionPlan::investorPass()->update([
            'checkout_amount' => 12900,
            'checkout_currency' => 'KES',
            'checkout_policy' => 'Administrator-set KES amount.',
        ]);

        Cache::flush();
    }

    public function test_sandbox_stk_push_uses_documented_endpoint_and_payload(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3599]),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => function ($request) {
                $body = $request->data();
                $this->assertSame('174379', $body['BusinessShortCode']);
                $this->assertSame('CustomerPayBillOnline', $body['TransactionType']);
                $this->assertSame(12900, $body['Amount']);
                $this->assertSame('254712345678', $body['PhoneNumber']);
                $this->assertSame('Investor Pass', $body['TransactionDesc']);
                $this->assertSame(12, strlen($body['AccountReference']));
                $this->assertSame(base64_encode('174379pass-key'.$body['Timestamp']), $body['Password']);

                return Http::response([
                    'ResponseCode' => '0',
                    'ResponseDescription' => 'Success. Request accepted for processing',
                    'CheckoutRequestID' => 'ws_CO_123',
                    'MerchantRequestID' => 'mr_123',
                ]);
            },
        ]);

        $accepted = app(DarajaMpesaGateway::class)->push(12900, '254712345678', 'INV-ABCDEFGH');

        $this->assertSame('ws_CO_123', $accepted->checkoutRequestId);
    }

    public function test_production_environment_selects_production_base_url(): void
    {
        config(['mpesa.environment' => 'production', 'mpesa.base_url' => null]);

        Http::fake([
            'https://api.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3599]),
            'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
                'CheckoutRequestID' => 'ws_CO_prod',
                'MerchantRequestID' => 'mr_prod',
            ]),
        ]);

        $accepted = app(DarajaMpesaGateway::class)->push(100, '254711111111', 'INV-12345678');

        $this->assertSame('ws_CO_prod', $accepted->checkoutRequestId);
        $this->assertSame('https://api.safaricom.co.ke', app(DarajaMpesaGateway::class)->resolvedBaseUrl());
    }

    public function test_oauth_token_is_cached(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3599]),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
                'CheckoutRequestID' => 'ws_CO_1',
                'MerchantRequestID' => 'mr_1',
            ]),
        ]);

        app(DarajaMpesaGateway::class)->push(100, '254711111111', 'INV-12345678');
        app(DarajaMpesaGateway::class)->push(100, '254722222222', 'INV-87654321');

        Http::assertSentCount(3);
    }

    public function test_oauth_failure_surfaces_without_leaking_secrets(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(\RuntimeException::class);

        app(DarajaMpesaGateway::class)->push(100, '254711111111', 'INV-12345678');
    }

    public function test_stk_response_code_other_than_zero_is_not_treated_as_success(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3599]),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '1',
                'ResponseDescription' => 'Invalid Access Token',
            ]),
        ]);

        $this->expectException(ValidationException::class);

        app(DarajaMpesaGateway::class)->push(100, '254711111111', 'INV-12345678');
    }

    public function test_phone_normalization_accepts_local_format(): void
    {
        $this->assertSame('254712345678', MpesaPhone::normalize('0712345678'));
        $this->assertSame('254712345678', MpesaPhone::normalize('254712345678'));
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        MpesaPhone::normalize('12345');
    }

    public function test_missing_configuration_blocks_push(): void
    {
        config(['mpesa.passkey' => null]);

        $this->expectException(ValidationException::class);

        app(DarajaMpesaGateway::class)->push(100, '254711111111', 'INV-12345678');
    }

    public function test_stk_query_parses_successful_receipt(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3599]),
            'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query' => Http::response([
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'MpesaReceiptNumber' => 'QHF123',
                'Amount' => 12900,
            ]),
        ]);

        $outcome = app(DarajaMpesaGateway::class)->query('ws_CO_123');

        $this->assertTrue($outcome->paid);
        $this->assertSame('QHF123', $outcome->receipt);
        $this->assertSame(12900, $outcome->amount);
    }

    public function test_checkout_uses_plan_kes_amount_not_browser_input(): void
    {
        $this->app->instance(MpesaGateway::class, new FakeMpesaGateway);

        $buyer = User::factory()->withRoles('buyer')->create();

        SubscriptionPlan::investorPass()->update(['checkout_amount' => 5000]);

        $this->actingAs($buyer)->post(route('buyer.subscription.checkout'), [
            'phone' => '0712345678',
            'idempotency_key' => 'plan-amount',
        ])->assertRedirect();

        $this->assertSame(5000, SubscriptionPayment::query()->first()->amount);
    }

    public function test_callback_path_token_authenticates_without_header(): void
    {
        $this->app->instance(MpesaGateway::class, new FakeMpesaGateway);
        config(['mpesa.callback_secret' => 'path-token', 'mpesa.timeout_minutes' => 2]);

        $buyer = User::factory()->withRoles('buyer')->create();
        $this->actingAs($buyer)->post(route('buyer.subscription.checkout'), [
            'phone' => '254712345678',
            'idempotency_key' => 'path-auth',
        ]);

        $payment = SubscriptionPayment::query()->firstOrFail();

        $this->post(route('payments.mpesa.callback', ['token' => 'wrong']), $this->payload($payment, 0, 'RCPT1'))
            ->assertForbidden();

        $this->post(route('payments.mpesa.callback', ['token' => 'path-token']), $this->payload($payment, 1032, null))
            ->assertNoContent();
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
                    'ResultDesc' => 'Cancelled by user.',
                    'CallbackMetadata' => ['Item' => $items],
                ],
            ],
        ];
    }
}
