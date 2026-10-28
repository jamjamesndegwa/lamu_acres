<?php

namespace App\Services\Subscription;

use App\Models\SubscriptionPlan;

class InvestorPassBilling
{
    public function mpesaAmountKes(SubscriptionPlan $plan): ?int
    {
        if ($plan->checkout_amount !== null && $plan->checkout_amount >= 1) {
            return (int) $plan->checkout_amount;
        }

        $listCurrency = strtoupper($plan->currency);

        if ($listCurrency === 'KES') {
            $kes = (int) round($plan->amount_cents / 100);

            return $kes >= 1 ? $kes : null;
        }

        $rate = $this->fxRate($listCurrency);

        if ($rate === null) {
            return null;
        }

        $kes = (int) round(($plan->amount_cents / 100) * $rate);

        return $kes >= 1 ? $kes : null;
    }

    public function usesManualCheckoutAmount(SubscriptionPlan $plan): bool
    {
        return $plan->checkout_amount !== null && $plan->checkout_amount >= 1;
    }

    public function mpesaCheckoutReady(SubscriptionPlan $plan): bool
    {
        if (! $plan->is_active) {
            return false;
        }

        $amount = $this->mpesaAmountKes($plan);

        if ($amount === null || $amount < 1) {
            return false;
        }

        if (filled($plan->checkout_policy)) {
            return true;
        }

        if ($this->usesManualCheckoutAmount($plan)) {
            return true;
        }

        if (strtoupper($plan->currency) === 'KES') {
            return true;
        }

        return $this->fxRate(strtoupper($plan->currency)) !== null;
    }

    public function checkoutPolicy(SubscriptionPlan $plan): ?string
    {
        if (filled($plan->checkout_policy)) {
            return $plan->checkout_policy;
        }

        return $this->conversionSummary($plan);
    }

    public function conversionSummary(SubscriptionPlan $plan): ?string
    {
        if ($this->usesManualCheckoutAmount($plan)) {
            return null;
        }

        $listCurrency = strtoupper($plan->currency);
        $kes = $this->mpesaAmountKes($plan);

        if ($kes === null) {
            return null;
        }

        if ($listCurrency === 'KES') {
            return 'M-Pesa collects KES '.number_format($kes).' matching the list price.';
        }

        $rate = $this->fxRate($listCurrency);

        if ($rate === null) {
            return null;
        }

        return 'M-Pesa checkout converts '.$plan->amountLabel().' at '
            .number_format($rate, 2).' KES per '.$listCurrency.' (KES '.number_format($kes).').';
    }

    private function fxRate(string $currency): ?float
    {
        $rate = config('subscription.fx_to_kes.'.$currency);

        if (! is_numeric($rate) || (float) $rate <= 0) {
            return null;
        }

        return (float) $rate;
    }
}
