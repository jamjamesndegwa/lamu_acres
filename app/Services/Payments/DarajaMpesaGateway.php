<?php

namespace App\Services\Payments;

use App\Contracts\Payments\MpesaGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DarajaMpesaGateway implements MpesaGateway
{
    public function push(int $amount, string $phone, string $reference): StkAccepted
    {
        $this->guardConfigured();
        $timestamp = now()->format('YmdHis');

        try {
            $response = Http::withToken($this->token())
                ->timeout(20)
                ->post($this->base().'/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => config('mpesa.shortcode'),
                    'Password' => $this->password($timestamp),
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => config('mpesa.shortcode'),
                    'PhoneNumber' => $phone,
                    'CallBackURL' => config('mpesa.callback_url'),
                    'AccountReference' => $reference,
                    'TransactionDesc' => 'Investor Pass',
                ]);
        } catch (ConnectionException $exception) {
            throw new MpesaTimeout('The provider did not accept the push.', 0, $exception);
        }

        $checkout = $response->json('CheckoutRequestID');
        $merchant = $response->json('MerchantRequestID');

        if (! $response->successful() || ! is_string($checkout) || ! is_string($merchant)) {
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
                ->timeout(20)
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
            return StkOutcome::paid((string) $response->json('MpesaReceiptNumber'), (int) $response->json('Amount'));
        }

        if ($code === 1037) {
            return StkOutcome::timedOut();
        }

        if ($code === null) {
            return StkOutcome::pending();
        }

        return StkOutcome::failed($code, $description);
    }

    private function guardConfigured(): void
    {
        foreach (['base_url', 'consumer_key', 'consumer_secret', 'passkey', 'shortcode', 'callback_url'] as $key) {
            if (blank(config('mpesa.'.$key))) {
                throw ValidationException::withMessages([
                    'phone' => 'M-Pesa is not configured. No request was sent.',
                ]);
            }
        }
    }

    private function token(): string
    {
        $response = Http::withBasicAuth((string) config('mpesa.consumer_key'), (string) config('mpesa.consumer_secret'))
            ->timeout(15)
            ->get($this->base().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('The provider did not issue an access token.');
        }

        return $token;
    }

    private function password(string $timestamp): string
    {
        return base64_encode((string) config('mpesa.shortcode').(string) config('mpesa.passkey').$timestamp);
    }

    private function base(): string
    {
        return rtrim((string) config('mpesa.base_url'), '/');
    }
}
