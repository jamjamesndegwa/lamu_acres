<?php

namespace App\Http\Controllers\Account;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\MpesaCheckout;
use App\Services\Payments\MpesaPhone;
use App\Services\Subscription\InvestorPassBilling;
use App\Services\Subscription\Memberships;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function show(Request $request, Memberships $memberships, InvestorPassBilling $billing): View
    {
        $plan = SubscriptionPlan::investorPass();
        $subscription = $memberships->find($request->user(), $plan);
        $memberships->expireIfDue($subscription);
        $subscription?->refresh()->load(['events', 'invoices.receipts', 'payments']);

        $routePrefix = $this->routePrefix($request);

        $mpesaAmountKes = $billing->mpesaAmountKes($plan);
        $checkoutReady = $billing->mpesaCheckoutReady($plan);

        $hasPendingPayment = $subscription !== null
            && $subscription->payments->contains(fn (SubscriptionPayment $payment): bool => $payment->status === PaymentStatus::Pending);

        return view('account.subscription.show', [
            'plan' => $plan,
            'subscription' => $subscription,
            'routePrefix' => $routePrefix,
            'checkoutReady' => $checkoutReady,
            'mpesaAmountKes' => $mpesaAmountKes,
            'mpesaConversionSummary' => $billing->conversionSummary($plan),
            'mpesaConfigured' => config('mpesa.driver') !== 'unconfigured',
            'hasPendingPayment' => $hasPendingPayment,
            'retryablePayments' => $subscription?->payments
                ->filter(fn (SubscriptionPayment $payment): bool => in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::TimedOut], true))
                ->values() ?? collect(),
            'latestPayment' => $subscription?->payments->sortByDesc('id')->first(),
        ]);
    }

    public function checkout(Request $request, MpesaCheckout $checkout): RedirectResponse
    {
        $this->assertCanManageSubscriptionPayment($request);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'idempotency_key' => ['required', 'string', 'max:80'],
            'billing_method' => ['nullable', 'string', 'in:mpesa'],
        ]);

        $phone = MpesaPhone::normalize($data['phone']);

        $checkout->start($request->user(), $phone, $data['idempotency_key']);

        return back()->with('status', 'The payment prompt was sent. Premium research stays closed until the provider confirms it.');
    }

    public function retry(Request $request, SubscriptionPayment $subscriptionPayment, MpesaCheckout $checkout): RedirectResponse
    {
        $this->assertCanManageSubscriptionPayment($request);
        abort_unless($subscriptionPayment->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);

        $checkout->retry($request->user(), $subscriptionPayment, $data['idempotency_key']);

        return back()->with('status', 'The payment prompt was sent again. Access is still closed.');
    }

    public function store(Request $request, Memberships $memberships): RedirectResponse
    {
        abort_unless($this->canPurchaseInvestorPass($request->user()), 403);
        abort_unless(SubscriptionPlan::investorPass()->is_active, 422);
        $memberships->request($request->user());

        return back()->with('status', 'Membership requested. Premium research stays closed until finance records it.');
    }

    public function cancel(Request $request, Memberships $memberships): RedirectResponse
    {
        $subscription = $memberships->find($request->user());
        abort_unless($subscription !== null, 404);
        $memberships->cancel($request->user(), $subscription);

        return back()->with('status', 'Investor Pass cancelled. Premium research and the Investor workspace are closed.');
    }

    private function routePrefix(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        return match (true) {
            str_starts_with($name, 'broker.') => 'broker',
            str_starts_with($name, 'seller.') => 'seller',
            str_starts_with($name, 'investor.') => 'investor',
            default => 'buyer',
        };
    }

    private function canPurchaseInvestorPass(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasPermission('workspace.buyer')
            || $user->hasPermission('workspace.broker')
            || $user->hasPermission('workspace.seller');
    }

    private function assertCanManageSubscriptionPayment(Request $request): void
    {
        $user = $request->user();
        $routeName = (string) $request->route()?->getName();

        if ($this->canPurchaseInvestorPass($user)) {
            return;
        }

        if (str_starts_with($routeName, 'investor.') && $user->hasActiveInvestorPass()) {
            return;
        }

        abort(403);
    }
}
