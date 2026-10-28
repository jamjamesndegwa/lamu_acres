@php
    use App\Enums\PaymentStatus;
    use App\Enums\SubscriptionStatus;

    $heading = $routePrefix === 'investor' ? 'My Subscription' : 'Investor Pass';
    $passActive = auth()->user()->hasActiveInvestorPass()
        || ($subscription && $subscription->status === SubscriptionStatus::Active);
    $statusLabel = $subscription?->status?->label()
        ?? ($passActive ? 'Active' : 'Not active');
    $renewalLabel = $subscription?->ends_at
        ? $subscription->ends_at->toFormattedDateString()
        : ($passActive ? 'Open-ended' : 'Not scheduled');
    $startsLabel = $subscription?->starts_at?->toFormattedDateString() ?? 'Not recorded';
    $checkoutLabel = $checkoutReady
        ? 'KES '.number_format($plan->checkout_amount)
        : 'Not configured';
    $canRequest = $routePrefix !== 'investor'
        && $plan->is_active
        && (! $subscription || in_array($subscription->status, [SubscriptionStatus::Expired, SubscriptionStatus::Cancelled], true));
    $canCheckout = $checkoutReady
        && $mpesaConfigured
        && ! $hasPendingPayment
        && ($routePrefix !== 'investor' || $passActive);
    $premiumResearchUrl = $routePrefix === 'investor'
        ? route('investor.reports.index')
        : route('intelligence.reports.index');
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
    @if (session('status'))
        <p class="mt-4 max-w-5xl rounded-xl border border-lamu-200 bg-lamu-50 px-4 py-3 text-sm text-lamu-900">{{ session('status') }}</p>
    @endif

    <div class="mt-4 max-w-5xl rounded-2xl border border-lamu-800 bg-lamu-900 px-5 py-5 text-white shadow-md">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/80">{{ $plan->name }}</p>
                <p class="home-serif mt-1 text-2xl font-semibold text-white">{{ $plan->amountLabel() }}</p>
                <p class="mt-1 text-sm text-white/80">Billed every {{ $plan->interval }} · payment method: M-Pesa (KES) when configured</p>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/90">{{ $plan->terms }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($passActive)
                    <a href="{{ $premiumResearchUrl }}" class="rounded-full bg-white px-4 py-2 text-sm font-medium text-lamu-900">Open premium research</a>
                @elseif ($canRequest)
                    <form method="POST" action="{{ route($routePrefix.'.subscription.store') }}">@csrf
                        <button type="submit" class="rounded-full bg-white px-4 py-2 text-sm font-medium text-lamu-900">Request Investor Pass</button>
                    </form>
                @endif
                @if ($canCheckout)
                    <button type="button" class="rounded-full border border-white/40 px-4 py-2 text-sm font-medium text-white hover:bg-white/10" onclick="document.getElementById('investor-checkout').showModal()">
                        {{ $passActive ? 'Renew with M-Pesa' : 'Pay with M-Pesa' }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <section class="mt-6 max-w-5xl" aria-labelledby="pass-status-heading">
        <h2 id="pass-status-heading" class="sr-only">Membership status</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi label="Status" :value="$statusLabel" />
            <x-kpi label="Started" :value="$startsLabel" />
            <x-kpi label="Paid through" :value="$renewalLabel" />
            <x-kpi label="M-Pesa amount" :value="$checkoutLabel" />
        </div>
    </section>

    @if ($hasPendingPayment)
        <div class="mt-6 max-w-5xl rounded-xl border border-amber-300 bg-amber-50 px-4 py-4 text-sm text-stone-800">
            <p class="font-semibold text-lamu-900">Payment pending</p>
            @if ($passActive)
                <p class="mt-1">An M-Pesa prompt is waiting for confirmation. Your current pass stays open until it expires or is cancelled; the renewal period extends only after a verified receipt is reconciled.</p>
            @else
                <p class="mt-1">An M-Pesa prompt is waiting for confirmation. Premium research and the Investor workspace stay closed until finance reconciles a successful payment.</p>
            @endif
        </div>
    @endif

    @if ($checkoutReady && ! $mpesaConfigured)
        <div class="mt-6 max-w-5xl rounded-xl border border-amber-300 bg-amber-50 px-4 py-4 text-sm text-stone-800">
            <p class="font-semibold text-lamu-900">M-Pesa checkout unavailable</p>
            <p class="mt-1">A KES amount is configured, but the payment gateway is not set up in this environment. You can still request membership for staff to record manually.</p>
        </div>
    @endif

    <div class="mt-8 max-w-5xl grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">What Investor Pass provides</h2>
            <ul class="mt-4 space-y-3 text-sm text-stone-700">
                <li class="flex gap-3"><span class="mt-0.5 text-lamu-700" aria-hidden="true">✓</span><span>Premium research reports, planning records, price observatory, imagery comparisons, and development tracking in your Investor workspace.</span></li>
                <li class="flex gap-3"><span class="mt-0.5 text-lamu-700" aria-hidden="true">✓</span><span>{{ $plan->interval === 'year' ? 'Annual' : ucfirst($plan->interval) }} membership recorded after verified payment or staff activation.</span></li>
                <li class="flex gap-3"><span class="mt-0.5 text-stone-400" aria-hidden="true">✕</span><span>Seller identity, parcel numbers, and exact directions to private holdings stay closed.</span></li>
            </ul>
            @if ($checkoutReady)
                <p class="mt-4 border-t border-sand-100 pt-4 text-sm text-stone-600">M-Pesa collects KES {{ number_format($plan->checkout_amount) }} (recorded separately from the {{ $plan->currency }} list price). {{ $plan->checkout_policy }}</p>
            @else
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-stone-800">No payable KES amount has been set yet, so an M-Pesa prompt cannot be sent. You can still request membership for staff to record manually.</p>
            @endif

            <h3 class="mt-6 text-sm font-semibold text-lamu-900">After you pay</h3>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-stone-700">
                <li>Approve the STK prompt on your phone.</li>
                <li>Lamu Acres reconciles the provider result (this can take a few minutes).</li>
                <li>Your pass activates only after a verified receipt is on file—not when the prompt is sent.</li>
                <li>You receive a queued activation email when access opens; expiry reminders are sent before your paid-through date.</li>
            </ol>
        </section>

        <section class="rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">Membership on this account</h2>
            @if ($subscription && $subscription->status === SubscriptionStatus::Active)
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-700">
                    <x-status-badge :label="$subscription->status->label()" class="bg-lamu-100 text-lamu-900" />
                    Premium research is open until {{ $subscription->ends_at?->toFormattedDateString() ?? 'the pass is cancelled' }}.
                </p>
            @elseif ($subscription && $subscription->status === SubscriptionStatus::Pending)
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-700">
                    <x-status-badge :label="$subscription->status->label()" />
                    Staff or verified M-Pesa payment must activate the pass before premium research opens.
                </p>
            @elseif ($subscription && $subscription->status === SubscriptionStatus::Expired)
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-700">
                    <x-status-badge :label="$subscription->status->label()" />
                    Your paid-through date has passed. Renew here to reopen premium research.
                </p>
            @elseif ($subscription && $subscription->status === SubscriptionStatus::Cancelled)
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-700">
                    <x-status-badge :label="$subscription->status->label()" />
                    Access was closed when you cancelled. Request membership or pay again to reopen.
                </p>
            @elseif ($passActive)
                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-700">
                    <x-status-badge label="Active" class="bg-lamu-100 text-lamu-900" />
                    An Investor Pass entitlement is active. Premium research is open.
                </p>
            @else
                <x-workspace.empty
                    class="mt-4"
                    title="No membership yet"
                    message="Premium research stays closed until staff activate your pass or M-Pesa payment is confirmed."
                />
            @endif

            @if ($routePrefix === 'investor' && ! $passActive)
                <p class="mt-4 text-sm text-stone-600">If your pass expired, open the Buyer workspace subscription page to renew.</p>
            @endif

            @if ($subscription && $subscription->status === SubscriptionStatus::Active && $routePrefix !== 'staff')
                <form
                    method="POST"
                    action="{{ route($routePrefix.'.subscription.cancel') }}"
                    data-confirm="Cancel your Investor Pass now? Premium research and the Investor workspace will close immediately—not at the end of your paid-through date."
                    class="mt-5 border-t border-sand-100 pt-4"
                >
                    @csrf
                    <p class="text-sm text-stone-600">Cancellation takes effect immediately. Effective date: {{ now()->toFormattedDateString() }}.</p>
                    <button type="submit" class="mt-3 rounded-full border border-sand-300 px-4 py-2 text-sm font-medium text-stone-800 hover:border-red-300 hover:text-red-800">Cancel pass</button>
                </form>
                <p class="mt-3 text-xs text-stone-500">Cancel-at-period-end (keep access until {{ $subscription->ends_at?->toFormattedDateString() }}) is not available today—it requires a separate commercial approval.</p>
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

        @if ($retryablePayments->isNotEmpty() && $checkoutReady && $mpesaConfigured)
            <div class="mt-6 border-t border-sand-100 pt-4">
                <h3 class="text-sm font-semibold text-lamu-900">Retry a failed payment</h3>
                <ul class="mt-3 space-y-3">
                    @foreach ($retryablePayments as $payment)
                        <li class="rounded-lg border border-sand-100 bg-sand-50 px-4 py-3 text-sm text-stone-700">
                            <p>{{ $payment->created_at?->toDayDateTimeString() }} · KES {{ number_format($payment->amount) }} · {{ $payment->status->label() }}</p>
                            <form method="POST" action="{{ route($routePrefix.'.subscription.retry', $payment) }}" data-turbo-frame="_top" data-confirm="Send another M-Pesa prompt for this payment?" class="mt-2">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-xs font-medium text-white">Retry payment prompt</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
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
    </section>

    @if ($canCheckout)
        <dialog id="investor-checkout" class="w-full max-w-lg rounded-2xl border border-sand-200 bg-white p-6 shadow-xl backdrop:bg-stone-900/50">
            <form method="dialog" class="text-right">
                <button class="text-sm font-medium text-stone-500 hover:text-lamu-900">Close</button>
            </form>
            <h2 class="home-serif text-xl font-semibold text-lamu-900">{{ $passActive ? 'Renew Investor Pass' : 'Investor Pass checkout' }}</h2>
            <p class="mt-2 text-sm text-stone-600">Enter the M-Pesa number that should receive the STK prompt for KES {{ number_format($plan->checkout_amount) }}. @if ($passActive) Your renewal period extends only after payment is confirmed. @else Premium research stays closed until payment is confirmed. @endif</p>
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
