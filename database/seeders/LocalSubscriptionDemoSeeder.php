<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReceipt;
use App\Models\User;
use App\Services\Subscription\InvestorPassBilling;
use App\Services\Subscription\Memberships;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LocalSubscriptionDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->seedMburuMpesaMembership();
    }

    private function seedMburuMpesaMembership(): void
    {
        $mburu = User::query()->where('email', 'mburu@lamuacres.test')->first();

        if ($mburu === null) {
            return;
        }

        if ($mburu->hasActiveInvestorPass()) {
            return;
        }

        $plan = SubscriptionPlan::investorPass();
        $billing = app(InvestorPassBilling::class);
        $kes = $billing->mpesaAmountKes($plan) ?? 12900;
        $memberships = app(Memberships::class);
        $subscription = $memberships->find($mburu, $plan);

        if ($subscription === null) {
            $subscription = $memberships->request($mburu);
        }

        if (SubscriptionPayment::query()->where('user_id', $mburu->id)->where('status', PaymentStatus::Succeeded)->exists()) {
            return;
        }

        $invoice = SubscriptionInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'number' => 'INV-'.strtoupper(Str::random(8)),
            'amount' => $kes,
            'currency' => 'KES',
            'status' => 'paid',
            'policy' => (string) ($billing->checkoutPolicy($plan) ?? 'Local development M-Pesa seed.'),
        ]);

        $payment = SubscriptionPayment::query()->create([
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'user_id' => $mburu->id,
            'idempotency_key' => 'seed-mburu-mpesa-'.Str::lower(Str::random(8)),
            'phone' => '254712345678',
            'amount' => $kes,
            'currency' => 'KES',
            'status' => PaymentStatus::Succeeded,
            'checkout_request_id' => 'SEED-CHECKOUT-'.Str::upper(Str::random(6)),
            'provider_result_code' => '0',
            'provider_result_desc' => 'Local development seed payment.',
            'attempted_at' => now()->subHour(),
            'reconciled_at' => now()->subMinutes(55),
        ]);

        SubscriptionReceipt::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'provider_receipt' => 'SEED'.strtoupper(Str::random(8)),
            'amount' => $kes,
            'currency' => 'KES',
            'issued_at' => now()->subMinutes(55),
        ]);

        $memberships->recordVerifiedPayment(
            $subscription->fresh(),
            'M-Pesa receipt seeded for local development (mburu@lamuacres.test).',
            $kes * 100,
            'KES',
        );

        $this->command?->info('Seeded M-Pesa Investor Pass for mburu@lamuacres.test (KES '.number_format($kes).').');
    }
}
