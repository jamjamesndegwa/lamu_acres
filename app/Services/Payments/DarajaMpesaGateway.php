<?php

namespace App\Services\Payments;

use App\Contracts\Payments\MpesaGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DarajaMpesaGateway implements MpesaGateway
{
    public function push(int $amount, string $phone, string $reference): StkAccepted
    {
        $this->guardConfigured();
        $timestamp = now()->format('YmdHis');
        $accountReference = substr($reference, 0, 12);

        try {
            $response = Http::withToken($this->token())
                ->timeout((int) config('mpesa.http_timeout_seconds', 20))
                ->post($this->base().'/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => config('mpesa.shortcode'),
                    'Password' => $this->password($timestamp),
                    'Timestamp' => $timestamp,
                    'TransactionType' => (string) config('mpesa.transaction_type', 'CustomerPayBillOnline'),
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => config('mpesa.shortcode'),
                    'PhoneNumber' => $phone,
                    'CallBackURL' => config('mpesa.callback_url'),
                    'AccountReference' => $accountReference,
                    'TransactionDesc' => 'Investor Pass',
                ]);
        } catch (ConnectionException $exception) {
            throw new MpesaTimeout('The provider did not accept the push.', 0, $exception);
        }

        if ((string) $response->json('ResponseCode') !== '0') {
            throw ValidationException::withMessages([
                'phone' => (string) ($response->json('ResponseDescription') ?: 'The provider did not accept the push.'),
            ]);
        }

        $checkout = $response->json('CheckoutRequestID');
        $merchant = $response->json('MerchantRequestID');

        if (! is_string($checkout) || $checkout === '' || ! is_string($merchant) || $merchant === '') {
            throw new RuntimeException('The provider did not accept the push.');
        }

        return new StkAccepted($checkout, $merchant);
    }

    public function query(string $checkoutRequestId): StkOutcome
    {
        $this->guardConfigured();
        $timestamp = now()->format('YmdHis');

        try {
            $response = Http::withToken($this->token())
                ->timeout((int) config('mpesa.http_timeout_seconds', 20))
                ->post($this->base().'/mpesa/stkpushquery/v1/query', [
                    'BusinessShortCode' => config('mpesa.shortcode'),
                    'Password' => $this->password($timestamp),
                    'Timestamp' => $timestamp,
                    'CheckoutRequestID' => $checkoutRequestId,
                ]);
        } catch (ConnectionException $exception) {
            throw new MpesaTimeout('The provider did not answer the status request.', 0, $exception);
        }

        if ($response->serverError()) {
            throw new MpesaTimeout('The provider status request failed.');
        }

        $code = $response->json('ResultCode');
        $code = is_numeric($code) ? (int) $code : null;
        $description = (string) ($response->json('ResultDesc') ?: '');

        if ($code === 0) {
            $receipt = (string) ($response->json('MpesaReceiptNumber') ?: '');
            $amount = (int) round((float) ($response->json('Amount') ?? 0));

            if ($receipt === '' || $amount < 1) {
                return StkOutcome::pending();
            }

            return StkOutcome::paid($receipt, $amount);
        }

        if ($code === 1037) {
            return StkOutcome::timedOut();
        }

        if ($code === null) {
            return StkOutcome::pending();
        }

        return StkOutcome::failed($code, $description);
    }

    public function resolvedBaseUrl(): string
    {
        return $this->base();
    }

    private function guardConfigured(): void
    {
        foreach (['consumer_key', 'consumer_secret', 'passkey', 'shortcode', 'callback_url'] as $key) {
            if (blank(config('mpesa.'.$key))) {
                throw ValidationException::withMessages([
                    'phone' => 'M-Pesa is not configured. No request was sent.',
                ]);
            }
        }

        if (blank($this->base())) {
            throw ValidationException::withMessages([
                'phone' => 'M-Pesa is not configured. No request was sent.',
            ]);
        }
    }

    private function token(): string
    {
        $cacheKey = 'mpesa.daraja.oauth.'.md5((string) config('mpesa.consumer_key'));

        $token = Cache::remember($cacheKey, max(60, (int) config('mpesa.oauth_cache_seconds', 3300)), function (): string {
            $response = Http::withBasicAuth((string) config('mpesa.consumer_key'), (string) config('mpesa.consumer_secret'))
                ->timeout((int) config('mpesa.http_timeout_seconds', 20))
                ->get($this->base().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('The provider did not issue an access token.');
            }

            return $token;
        });

        return $token;
    }

    private function password(string $timestamp): string
    {
        return base64_encode((string) config('mpesa.shortcode').(string) config('mpesa.passkey').$timestamp);
    }

    private function base(): string
    {
        $configured = config('mpesa.base_url');

        if (filled($configured)) {
            return rtrim((string) $configured, '/');
        }

        return config('mpesa.environment') === 'production'
            ? rtrim((string) config('mpesa.production_base_url'), '/')
            : rtrim((string) config('mpesa.sandbox_base_url'), '/');
    }
}
