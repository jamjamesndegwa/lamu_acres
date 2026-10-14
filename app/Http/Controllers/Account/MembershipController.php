<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Payments\MpesaCheckout;
use App\Services\Subscription\Memberships;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function show(Request $request, Memberships $memberships): View
    {
        $plan = SubscriptionPlan::investorPass();
        $subscription = $memberships->find($request->user(), $plan);
        $memberships->expireIfDue($subscription);
        $subscription?->refresh()->load(['events', 'invoices.receipts', 'payments']);

        return view('account.subscription.show', [
            'plan' => $plan,
            'subscription' => $subscription,
            'routePrefix' => str_starts_with((string) $request->route()?->getName(), 'broker.') ? 'broker' : (str_starts_with((string) $request->route()?->getName(), 'investor.') ? 'investor' : 'buyer'),
        ]);
    }

    public function checkout(Request $request, MpesaCheckout $checkout): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('workspace.buyer') || $request->user()->hasPermission('workspace.broker'), 403);

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^254(7|1)\d{8}$/'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);

        $checkout->start($request->user(), $data['phone'], $data['idempotency_key']);

        return back()->with('status', 'The payment prompt was sent. Premium research stays closed until the provider confirms it.');
    }

    public function retry(Request $request, SubscriptionPayment $subscriptionPayment, MpesaCheckout $checkout): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('workspace.buyer') || $request->user()->hasPermission('workspace.broker'), 403);

        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);

        $checkout->retry($request->user(), $subscriptionPayment, $data['idempotency_key']);

        return back()->with('status', 'The payment prompt was sent again. Access is still closed.');
    }

    public function store(Request $request, Memberships $memberships): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('workspace.buyer') || $request->user()->hasPermission('workspace.broker'), 403);
        abort_unless(SubscriptionPlan::investorPass()->is_active, 422);
        $memberships->request($request->user());

        return back()->with('status', 'Membership requested. Premium research stays closed until finance records it.');
    }

    public function cancel(Request $request, Memberships $memberships): RedirectResponse
    {
        $subscription = $memberships->find($request->user());
        abort_unless($subscription !== null, 404);
        $memberships->cancel($request->user(), $subscription);

        return back()->with('status', 'Investor Pass cancelled.');
    }
}
