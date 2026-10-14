<x-workspace-layout heading="Investor Pass">
    <p class="mt-3 max-w-3xl text-stone-700">{{ $plan->name }} · list price {{ $plan->amountLabel() }}</p>
    <p class="mt-3 max-w-3xl text-sm text-stone-600">{{ $plan->terms }}</p>
    @if ($plan->checkout_amount)
        <p class="mt-3 max-w-3xl text-sm text-stone-700">M-Pesa collects KES {{ number_format($plan->checkout_amount) }}. That figure is recorded separately from the list price. This application does not convert currencies.</p>
        <p class="mt-2 max-w-3xl text-sm text-stone-600">{{ $plan->checkout_policy }}</p>
    @else
        <p class="mt-3 max-w-3xl text-sm text-stone-600">No payable KES amount has been set, so a payment prompt cannot be sent.</p>
    @endif
    @if ($subscription)
        <p class="mt-4">Status: {{ $subscription->status->label() }} @if ($subscription->ends_at) · until {{ $subscription->ends_at->toFormattedDateString() }} @endif</p>
        <h2 class="mt-6 font-semibold">Purchase history</h2>
        <ul class="mt-2 text-sm">
            @foreach ($subscription->events as $event)
                <li>{{ $event->created_at?->toDayDateTimeString() }} · {{ $event->action }} @if ($event->amount_cents !== null) · {{ $event->currency }} {{ number_format($event->amount_cents / 100, 2) }} @endif · {{ $event->note }}</li>
            @endforeach
        </ul>
        @if ($subscription->status === \App\Enums\SubscriptionStatus::Active && $routePrefix !== 'staff')
            <form method="POST" action="{{ route($routePrefix.'.subscription.cancel') }}" class="mt-4">@csrf <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Cancel pass</button></form>
        @endif
        @if ($subscription->payments->isNotEmpty())
            <h2 class="mt-6 font-semibold">Payments</h2>
            <ul class="mt-2 text-sm">
                @foreach ($subscription->payments as $payment)
                    <li>{{ $payment->created_at?->toDayDateTimeString() }} · {{ $payment->currency }} {{ number_format($payment->amount) }} · {{ $payment->status->label() }}</li>
                @endforeach
            </ul>
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
    @elseif (auth()->user()->hasActiveInvestorPass())
        <p class="mt-4">An Investor Pass entitlement is active on this account.</p>
    @else
        <p class="mt-4">No membership is active. Premium research stays closed.</p>
    @endif
    @if ($routePrefix !== 'investor' && $plan->is_active && (! $subscription || ! in_array($subscription->status, [\App\Enums\SubscriptionStatus::Pending, \App\Enums\SubscriptionStatus::Active], true)))
        <form method="POST" action="{{ route($routePrefix.'.subscription.store') }}" class="mt-4">@csrf <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Request Investor Pass</button></form>
    @endif
    @if ($routePrefix !== 'investor' && $plan->checkout_amount && $plan->is_active)
        <form method="POST" action="{{ route($routePrefix.'.subscription.checkout') }}" class="mt-4 max-w-md space-y-3">
            @csrf
            <label class="block text-sm">M-Pesa phone
                <input name="phone" value="{{ old('phone') }}" placeholder="2547XXXXXXXX" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Send payment prompt</button>
            <p class="text-sm text-stone-600">Sending the prompt does not open premium research.</p>
        </form>
    @endif
</x-workspace-layout>
