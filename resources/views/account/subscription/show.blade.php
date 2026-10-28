@php
    use App\Enums\SubscriptionStatus;

    $heading = $routePrefix === 'investor' ? 'Subscription' : 'Investor Pass';
    $passActive = auth()->user()->hasActiveInvestorPass()
        || ($subscription && $subscription->status === SubscriptionStatus::Active);
    $statusLabel = $subscription?->status?->label()
        ?? ($passActive ? 'Active' : 'Not active');
    $renewalLabel = $subscription?->ends_at
        ? $subscription->ends_at->toFormattedDateString()
        : ($passActive ? 'Open-ended' : 'Not scheduled');
    $checkoutLabel = $plan->checkout_amount
        ? 'KES '.number_format($plan->checkout_amount)
        : 'Not configured';
    $canRequest = $routePrefix !== 'investor'
        && $plan->is_active
        && (! $subscription || ! in_array($subscription->status, [SubscriptionStatus::Pending, SubscriptionStatus::Active], true));
    $canCheckout = $routePrefix !== 'investor' && $plan->checkout_amount && $plan->is_active;
    $paymentItems = collect();
    if ($subscription) {
        foreach ($subscription->payments as $payment) {
            $paymentItems->push([
                'when' => $payment->created_at?->toDayDateTimeString(),
                'text' => $payment->currency.' '.number_format($payment->amount).' · '.$payment->status->label(),
            ]);
        }
        foreach ($subscription->events as $event) {
            $paymentItems->push([
                'when' => $event->created_at?->toDayDateTimeString(),
                'text' => $event->action
                    .($event->amount_cents !== null ? ' · '.$event->currency.' '.number_format($event->amount_cents / 100, 2) : '')
                    .($event->note ? ' · '.$event->note : ''),
            ]);
        }
        $paymentItems = $paymentItems->sortByDesc('when')->values();
    }
@endphp

<x-workspace-layout :heading="$heading">
    <div class="mt-4 max-w-5xl rounded-2xl border border-lamu-800 bg-lamu-900 px-5 py-5 text-white shadow-md">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/80">{{ $plan->name }}</p>
                <p class="home-serif mt-1 text-2xl font-semibold text-white">{{ $plan->amountLabel() }}</p>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/90">{{ $plan->terms }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($passActive)
                    <a href="{{ route('intelligence.reports.index') }}" data-turbo-frame="_top" class="rounded-full bg-white px-4 py-2 text-sm font-medium text-lamu-900">Open premium research</a>
                @elseif ($canRequest)
                    <form method="POST" action="{{ route($routePrefix.'.subscription.store') }}">@csrf
                        <button type="submit" class="rounded-full bg-white px-4 py-2 text-sm font-medium text-lamu-900">Request Investor Pass</button>
                    </form>
                @endif
                @if ($canCheckout)
                    <button type="button" class="rounded-full border border-white/40 px-4 py-2 text-sm font-medium text-white hover:bg-white/10" onclick="document.getElementById('investor-checkout').showModal()">Send payment prompt</button>
                @endif
            </div>
        </div>
    </div>

    <section class="mt-6 max-w-5xl" aria-labelledby="pass-status-heading">
        <h2 id="pass-status-heading" class="sr-only">Membership status</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-kpi label="Status" :value="$statusLabel" />
            <x-kpi label="Renewal" :value="$renewalLabel" />
            <x-kpi label="M-Pesa checkout" :value="$checkoutLabel" />
        </div>
    </section>

    <div class="mt-8 max-w-5xl grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">What the pass includes</h2>
            <ul class="mt-4 space-y-3 text-sm text-stone-700">
                <li class="flex gap-3"><span class="mt-0.5 text-lamu-700" aria-hidden="true">✓</span><span>Premium research reports, planning records, and investor intelligence in your workspace.</span></li>
                <li class="flex gap-3"><span class="mt-0.5 text-lamu-700" aria-hidden="true">✓</span><span>Annual membership recorded by the Lamu Acres finance desk after verified payment.</span></li>
                <li class="flex gap-3"><span class="mt-0.5 text-stone-400" aria-hidden="true">✕</span><span>Seller identity, parcel numbers, and exact directions to private holdings stay closed.</span></li>
            </ul>
            @if ($plan->checkout_amount)
                <p class="mt-4 border-t border-sand-100 pt-4 text-sm text-stone-600">M-Pesa collects KES {{ number_format($plan->checkout_amount) }} (recorded separately from the USD list price). {{ $plan->checkout_policy }}</p>
            @else
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-stone-800">No payable KES amount has been set yet, so a payment prompt cannot be sent. You can still request membership for staff to record manually.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">Active entitlement</h2>
            @if ($subscription && $subscription->status === SubscriptionStatus::Active)
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-700">
                    <x-status-badge :label="$subscription->status->label()" class="bg-lamu-100 text-lamu-900" />
                    Premium research is open. Seller identity, parcel numbers, and exact directions stay closed.
                </p>
            @elseif ($passActive)
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-700">
                    <x-status-badge label="Active" class="bg-lamu-100 text-lamu-900" />
                    An Investor Pass entitlement is active. Premium research is open.
                </p>
            @elseif ($subscription)
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-700">
                    <x-status-badge :label="$subscription->status->label()" />
                    Premium research stays closed until a verified payment is on file.
                </p>
            @else
                <x-workspace.empty
                    class="mt-4"
                    title="No membership yet"
                    message="Premium research stays closed until staff activate your pass or M-Pesa payment is confirmed."
                />
            @endif

            <h3 class="mt-6 text-sm font-semibold text-lamu-900">Renewal</h3>
            @if ($subscription?->ends_at)
                <p class="mt-2 text-sm text-stone-700">Renews or expires on <span class="font-medium text-lamu-900">{{ $subscription->ends_at->toFormattedDateString() }}</span>.</p>
            @elseif ($passActive)
                <p class="mt-2 text-sm text-stone-700">No end date is recorded. Access stays open until the pass is cancelled or finance sets a renewal date.</p>
            @else
                <p class="mt-2 text-sm text-stone-600">No renewal is scheduled.</p>
            @endif

            @if ($subscription && $subscription->status === SubscriptionStatus::Active && $routePrefix !== 'staff')
                <form method="POST" action="{{ route($routePrefix.'.subscription.cancel') }}" data-confirm="Cancel your Investor Pass? Premium research will close." class="mt-5 border-t border-sand-100 pt-4">
                    @csrf
                    <button type="submit" class="rounded-full border border-sand-300 px-4 py-2 text-sm font-medium text-stone-800 hover:border-red-300 hover:text-red-800">Cancel pass</button>
                </form>
            @endif
        </section>
    </div>

    <section class="mt-8 max-w-5xl rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">Payment history</h2>
        @if ($subscription && ($subscription->payments->isNotEmpty() || $subscription->events->isNotEmpty()))
            <x-activity-timeline :items="$paymentItems->all()" empty="No payment has been recorded." />
        @else
            <p class="mt-3 text-sm text-stone-600">No payment has been recorded.</p>
        @endif

        @if ($subscription && $subscription->invoices->isNotEmpty())
            <h3 class="mt-6 text-sm font-semibold text-lamu-900">Invoices and receipts</h3>
            <ul class="mt-3 space-y-2 text-sm text-stone-700">
                @foreach ($subscription->invoices as $invoice)
                    <li class="rounded-lg border border-sand-100 bg-sand-50 px-3 py-2">
                        {{ $invoice->number }} · {{ $invoice->currency }} {{ number_format($invoice->amount) }} · {{ $invoice->status }}
                        @foreach ($invoice->receipts as $receipt)
                            · receipt {{ $receipt->provider_receipt }}
                        @endforeach
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($subscription)
            <h3 class="mt-6 text-sm font-semibold text-lamu-900">Purchase history</h3>
            @if ($subscription->events->isNotEmpty())
                <ul class="mt-3 space-y-2 text-sm text-stone-700">
                    @foreach ($subscription->events as $event)
                        <li class="rounded-lg border border-sand-100 px-3 py-2">
                            {{ $event->created_at?->toDayDateTimeString() }} · {{ $event->action }}
                            @if ($event->amount_cents !== null)
                                · {{ $event->currency }} {{ number_format($event->amount_cents / 100, 2) }}
                            @endif
                            @if ($event->note)
                                · {{ $event->note }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-stone-600">No purchase events recorded.</p>
            @endif
        @endif
    </section>

    @if ($canCheckout)
        <dialog id="investor-checkout" class="w-full max-w-lg rounded-2xl border border-sand-200 bg-white p-6 shadow-xl backdrop:bg-stone-900/50">
            <form method="dialog" class="text-right">
                <button class="text-sm font-medium text-stone-500 hover:text-lamu-900">Close</button>
            </form>
            <h2 class="home-serif text-xl font-semibold text-lamu-900">Investor Pass checkout</h2>
            <p class="mt-2 text-sm text-stone-600">Enter the M-Pesa number that should receive the STK prompt. Premium research stays closed until payment is confirmed.</p>
            <form method="POST" action="{{ route($routePrefix.'.subscription.checkout') }}" data-turbo-frame="_top" data-confirm="Send this payment prompt?" class="mt-4 space-y-4">
                @csrf
                <label class="block text-sm font-medium text-lamu-900">M-Pesa phone
                    <input name="phone" value="{{ old('phone') }}" placeholder="2547XXXXXXXX" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2 shadow-sm">
                </label>
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send payment prompt</button>
            </form>
        </dialog>
    @endif
</x-workspace-layout>
