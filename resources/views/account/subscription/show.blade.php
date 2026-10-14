<x-workspace-layout :heading="$routePrefix === 'investor' ? 'Subscription' : 'Investor Pass'">
    <p class="mt-3 max-w-3xl text-stone-700">{{ $plan->name }} · list price {{ $plan->amountLabel() }}</p>
    <p class="mt-3 max-w-3xl text-sm text-stone-600">{{ $plan->terms }}</p>
    <p class="mt-3 max-w-3xl text-sm text-stone-700">An active entitlement opens premium research. It does not unlock seller identity, parcel numbers, or exact directions to a private holding.</p>
    @if ($plan->checkout_amount)
        <p class="mt-3 max-w-3xl text-sm text-stone-700">M-Pesa collects KES {{ number_format($plan->checkout_amount) }}. That figure is recorded separately from the list price. This application does not convert currencies.</p>
        <p class="mt-2 max-w-3xl text-sm text-stone-600">{{ $plan->checkout_policy }}</p>
    @else
        <p class="mt-3 max-w-3xl text-sm text-stone-600">No payable KES amount has been set, so a payment prompt cannot be sent.</p>
    @endif
    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">Active entitlement</h2>
        @if ($subscription && $subscription->status === \App\Enums\SubscriptionStatus::Active)
            <p class="mt-2 text-sm text-stone-700">Status: {{ $subscription->status->label() }}. Premium research is open. Seller identity, parcel numbers, and exact directions stay closed.</p>
        @elseif (auth()->user()->hasActiveInvestorPass())
            <p class="mt-2 text-sm text-stone-700">An Investor Pass entitlement is active. Premium research is open. Seller identity, parcel numbers, and exact directions stay closed.</p>
        @elseif ($subscription)
            <p class="mt-2 text-sm text-stone-700">Status: {{ $subscription->status->label() }}. Premium research stays closed until a verified payment is on file.</p>
        @else
            <p class="mt-2 text-sm text-stone-700">No membership is active. Premium research stays closed.</p>
        @endif
    </section>
    <section class="mt-4 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">Renewal</h2>
        @if ($subscription?->ends_at)
            <p class="mt-2 text-sm text-stone-700">Renewal date {{ $subscription->ends_at->toFormattedDateString() }}.</p>
        @elseif (auth()->user()->hasActiveInvestorPass())
            <p class="mt-2 text-sm text-stone-700">No renewal date is recorded. Access stays open until the pass is cancelled or an end date is set.</p>
        @else
            <p class="mt-2 text-sm text-stone-700">No renewal is scheduled.</p>
        @endif
    </section>
    @if ($subscription)
        <h2 class="mt-6 font-semibold">Purchase history</h2>
        <ul class="mt-2 text-sm">
            @foreach ($subscription->events as $event)
                <li>{{ $event->created_at?->toDayDateTimeString() }} · {{ $event->action }} @if ($event->amount_cents !== null) · {{ $event->currency }} {{ number_format($event->amount_cents / 100, 2) }} @endif · {{ $event->note }}</li>
            @endforeach
        </ul>
        @if ($subscription->status === \App\Enums\SubscriptionStatus::Active && $routePrefix !== 'staff')
            <form method="POST" action="{{ route($routePrefix.'.subscription.cancel') }}" class="mt-4">@csrf <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Cancel pass</button></form>
        @endif
        <h2 class="mt-6 font-semibold">Payment history</h2>
        @if ($subscription->payments->isNotEmpty())
            <h3 class="mt-3 text-sm font-medium text-lamu-900">Payments</h3>
            <ul class="mt-2 text-sm">
                @foreach ($subscription->payments as $payment)
                    <li>{{ $payment->created_at?->toDayDateTimeString() }} · {{ $payment->currency }} {{ number_format($payment->amount) }} · {{ $payment->status->label() }}</li>
                @endforeach
            </ul>
        @else
            <p class="mt-2 text-sm text-stone-600">No payment has been recorded.</p>
        @endif
        @if ($subscription->invoices->isNotEmpty())
            <h2 class="mt-6 font-semibold">Invoices and receipts</h2>
            <ul class="mt-2 text-sm">
                @foreach ($subscription->invoices as $invoice)
                    <li>
                        {{ $invoice->number }} · {{ $invoice->currency }} {{ number_format($invoice->amount) }} · {{ $invoice->status }}
                        @foreach ($invoice->receipts as $receipt)
                            · receipt {{ $receipt->provider_receipt }}
                        @endforeach
                    </li>
                @endforeach
            </ul>
        @endif
    @else
        <h2 class="mt-6 font-semibold">Payment history</h2>
        <p class="mt-2 text-sm text-stone-600">No payment has been recorded.</p>
    @endif
    @if ($routePrefix !== 'investor' && $plan->is_active && (! $subscription || ! in_array($subscription->status, [\App\Enums\SubscriptionStatus::Pending, \App\Enums\SubscriptionStatus::Active], true)))
        <form method="POST" action="{{ route($routePrefix.'.subscription.store') }}" class="mt-4">@csrf <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Request Investor Pass</button></form>
    @endif
    @if ($routePrefix !== 'investor' && $plan->checkout_amount && $plan->is_active)
        <button type="button" class="mt-4 rounded-full bg-lamu-900 px-5 py-3 text-sm text-white" onclick="document.getElementById('investor-checkout').showModal()">Send payment prompt</button>
        <dialog id="investor-checkout" class="w-full max-w-lg rounded-2xl p-6 backdrop:bg-stone-900/50">
            <form method="dialog" class="text-right"><button class="text-sm text-stone-500">Close</button></form>
            <h2 class="text-xl font-semibold text-lamu-900">Investor Pass checkout</h2>
            <form method="POST" action="{{ route($routePrefix.'.subscription.checkout') }}" data-turbo-frame="_top" data-confirm="Send this payment prompt?" class="mt-4 space-y-3">
                @csrf
                <label class="block text-sm">M-Pesa phone
                    <input name="phone" value="{{ old('phone') }}" placeholder="2547XXXXXXXX" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                </label>
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Send payment prompt</button>
                <p class="text-sm text-stone-600">Sending the prompt does not open premium research.</p>
            </form>
        </dialog>
    @endif
</x-workspace-layout>
