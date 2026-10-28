<?php

namespace App\Services\Payments;

use App\Contracts\Payments\MpesaGateway;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReceipt;
use App\Models\User;
use App\Services\Subscription\InvestorPassBilling;
use App\Services\Subscription\Memberships;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MpesaCheckout
{
    public function __construct(
        private MpesaGateway $gateway,
        private Memberships $memberships,
        private InvestorPassBilling $billing,
    ) {}

    public function start(User $member, string $phone, string $idempotencyKey): SubscriptionPayment
    {
        $phone = MpesaPhone::normalize($phone);

        $existing = SubscriptionPayment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            abort_unless($existing->user_id === $member->id, 403);

            return $existing;
        }

        $plan = SubscriptionPlan::investorPass();
        $amount = $this->payableAmount($plan);
        $policy = (string) ($this->billing->checkoutPolicy($plan) ?? '');
        $subscription = $this->memberships->find($member, $plan);

        if ($subscription && in_array($subscription->status, [SubscriptionStatus::Pending, SubscriptionStatus::Active], true) === false) {
            $subscription = null;
        }

        if ($subscription === null) {
            $subscription = $this->memberships->request($member);
        }

        if ($subscription->payments()->where('status', PaymentStatus::Pending)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'A payment is already waiting for a result.',
            ]);
        }

        $invoice = SubscriptionInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'number' => $this->number(),
            'amount' => $amount,
            'currency' => 'KES',
            'status' => 'open',
            'policy' => $policy !== '' ? $policy : (string) $plan->checkout_policy,
        ]);

        $payment = SubscriptionPayment::query()->create([
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'user_id' => $member->id,
            'idempotency_key' => $idempotencyKey,
            'phone' => $phone,
            'amount' => $amount,
            'currency' => 'KES',
            'status' => PaymentStatus::Pending,
            'attempted_at' => now(),
            'expires_at' => now()->addMinutes(max(1, (int) config('mpesa.timeout_minutes'))),
        ]);

        try {
            $accepted = $this->gateway->push($amount, $phone, $invoice->number);
        } catch (MpesaTimeout $exception) {
            $payment->update([
                'status' => PaymentStatus::TimedOut,
                'provider_result_desc' => $exception->getMessage(),
                'reconciled_at' => now(),
            ]);

            return $payment;
        } catch (ValidationException $exception) {
            $payment->delete();
            $invoice->delete();

            throw $exception;
        }

        $payment->update([
            'checkout_request_id' => $accepted->checkoutRequestId,
            'merchant_request_id' => $accepted->merchantRequestId,
        ]);

        return $payment->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function acceptCallback(array $payload): void
    {
        $checkoutId = data_get($payload, 'Body.stkCallback.CheckoutRequestID');
        $resultCode = data_get($payload, 'Body.stkCallback.ResultCode');

        if (! is_string($checkoutId) || $checkoutId === '' || ! is_numeric($resultCode)) {
            throw ValidationException::withMessages([
                'callback' => 'The provider callback is incomplete.',
            ]);
        }

        $payment = SubscriptionPayment::query()->where('checkout_request_id', $checkoutId)->first();

        if ($payment === null || $payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $receipt = $this->callbackReceipt($payload);
        $resultDesc = data_get($payload, 'Body.stkCallback.ResultDesc');
        $callbackDesc = is_string($resultDesc) ? $resultDesc : null;

        $this->applyOutcome($payment, $this->ask($payment), (int) $resultCode, $receipt, $callbackDesc);
    }

    public function reconcileExpired(): int
    {
        $count = 0;

        SubscriptionPayment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->each(function (SubscriptionPayment $payment) use (&$count): void {
                $this->applyOutcome($payment, $this->ask($payment), null, null, null);
                $count++;
            });

        return $count;
    }

    public function retry(User $member, SubscriptionPayment $payment, string $idempotencyKey): SubscriptionPayment
    {
        abort_unless($payment->user_id === $member->id, 403);
        abort_unless(in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::TimedOut], true), 422);

        return $this->start($member, $payment->phone, $idempotencyKey);
    }

    private function ask(SubscriptionPayment $payment): StkOutcome
    {
        if ($payment->checkout_request_id === null) {
            return StkOutcome::timedOut();
        }

        try {
            return $this->gateway->query($payment->checkout_request_id);
        } catch (MpesaTimeout) {
            return StkOutcome::timedOut();
        }
    }

    private function applyOutcome(SubscriptionPayment $payment, StkOutcome $outcome, ?int $callbackCode, ?string $callbackReceipt, ?string $callbackDesc = null): void
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return;
        }

        if ($callbackCode !== null && $callbackCode !== 0) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'provider_result_code' => (string) $callbackCode,
                'provider_result_desc' => $callbackDesc ?: $outcome->description ?: 'The provider reported a failed payment.',
                'reconciled_at' => now(),
            ]);

            return;
        }

        $receiptAgrees = $callbackReceipt === null || $outcome->receipt === null || $callbackReceipt === $outcome->receipt;
        $confirmed = $outcome->paid
            && $outcome->resultCode === 0
            && $outcome->amount === $payment->amount
            && $receiptAgrees
            && ($callbackCode === null || $callbackCode === 0)
            && is_string($outcome->receipt)
            && $outcome->receipt !== '';

        if ($confirmed) {
            $this->markPaid($payment, $outcome->receipt);

            return;
        }

        $status = match (true) {
            $outcome->timedOut => PaymentStatus::TimedOut,
            $outcome->failed || ($callbackCode !== null && $callbackCode !== 0) => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        if ($payment->expires_at !== null && $payment->expires_at->lte(now()) && $status === PaymentStatus::Pending) {
            $status = PaymentStatus::TimedOut;
        }

        $payment->update([
            'status' => $status,
            'provider_result_code' => $outcome->resultCode !== null ? (string) $outcome->resultCode : ($callbackCode !== null ? (string) $callbackCode : $payment->provider_result_code),
            'provider_result_desc' => $outcome->description ?: $callbackDesc ?: $payment->provider_result_desc,
            'reconciled_at' => now(),
        ]);
    }

    private function markPaid(SubscriptionPayment $payment, string $receipt): void
    {
        DB::transaction(function () use ($payment, $receipt): void {
            $locked = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === PaymentStatus::Succeeded) {
                return;
            }

            if (SubscriptionReceipt::query()->where('provider_receipt', $receipt)->exists()) {
                return;
            }

            $locked->update([
                'status' => PaymentStatus::Succeeded,
                'provider_result_code' => '0',
                'provider_result_desc' => 'Reconciled with the provider.',
                'reconciled_at' => now(),
            ]);

            $locked->invoice->update(['status' => 'paid']);

            SubscriptionReceipt::query()->create([
                'payment_id' => $locked->id,
                'invoice_id' => $locked->invoice_id,
                'provider_receipt' => $receipt,
                'amount' => $locked->amount,
                'currency' => 'KES',
                'issued_at' => now(),
            ]);

            $locked->subscription->refresh();
            $this->memberships->recordVerifiedPayment(
                $locked->subscription,
                'M-Pesa receipt '.$receipt.'. Access starts only after the provider result was reconciled.',
                $locked->amount * 100,
                'KES',
            );
        });
    }

    private function payableAmount(SubscriptionPlan $plan): int
    {
        $amount = $this->billing->mpesaAmountKes($plan);

        if (! $this->billing->mpesaCheckoutReady($plan) || $amount === null) {
            throw ValidationException::withMessages([
                'phone' => 'A payable KES amount could not be calculated. Set a checkout amount or configure conversion for the plan currency.',
            ]);
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function callbackReceipt(array $payload): ?string
    {
        $items = data_get($payload, 'Body.stkCallback.CallbackMetadata.Item', []);

        if (! is_array($items)) {
            return null;
        }

        foreach ($items as $item) {
            if (is_array($item) && ($item['Name'] ?? null) === 'MpesaReceiptNumber' && is_string($item['Value'] ?? null)) {
                return $item['Value'];
            }
        }

        return null;
    }

    private function number(): string
    {
        do {
            $number = 'INV-'.strtoupper(Str::random(8));
        } while (SubscriptionInvoice::query()->where('number', $number)->exists());

        return $number;
    }
}
