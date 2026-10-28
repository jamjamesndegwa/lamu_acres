<x-workspace-layout heading="Investor Pass memberships">
    <p class="mt-3 max-w-3xl text-stone-700">Recording a membership does not take a card payment. Access starts only after activation, and it uses the member’s existing account.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Member', 'Status', 'Period', 'M-Pesa attempts', '']">
            @forelse ($subscriptions as $subscription)
                <tr class="border-b border-sand-200 align-top">
                    <td class="px-4 py-3">
                        <p>{{ $subscription->user->name }} · {{ $subscription->user->email }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ $subscription->plan?->name }} · list {{ $subscription->plan?->amountLabel() }}</p>
                    </td>
                    <td class="px-4 py-3"><x-status-badge :label="$subscription->status->label()" /></td>
                    <td class="px-4 py-3 text-sm">
                        <p>Starts {{ $subscription->starts_at?->toFormattedDateString() ?: '—' }}</p>
                        <p>Expires {{ $subscription->ends_at?->toFormattedDateString() ?: '—' }}</p>
                    </td>
                    <td class="px-4 py-3 text-xs text-stone-600">
                        @forelse ($subscription->payments->sortByDesc('id')->take(3) as $payment)
                            <p class="mb-2 rounded border border-sand-100 bg-sand-50 px-2 py-1">
                                KES {{ number_format($payment->amount) }} · {{ $payment->status->label() }}
                                @if ($payment->checkout_request_id)
                                    · checkout {{ $payment->checkout_request_id }}
                                @endif
                                @if ($payment->receipt)
                                    · receipt {{ $payment->receipt->provider_receipt }}
                                @endif
                                @if ($payment->provider_result_desc)
                                    · {{ $payment->provider_result_desc }}
                                @endif
                            </p>
                        @empty
                            <p>No M-Pesa attempt recorded.</p>
                        @endforelse
                    </td>
                    <td class="px-4 py-3">
                        @php $invoice = $subscription->invoices->sortByDesc('id')->first(); @endphp
                        @if ($invoice)
                            <p class="mb-2 text-xs text-stone-500">Invoice {{ $invoice->number }} · {{ $invoice->status }} · KES {{ number_format($invoice->amount) }}</p>
                        @endif
                        @if ($subscription->status === \App\Enums\SubscriptionStatus::Pending)
                            <form method="POST" action="{{ route('staff.memberships.activate', $subscription) }}" data-confirm="Activate {{ $subscription->plan?->name ?? 'Investor Pass' }} for {{ $subscription->user->name }} ({{ $subscription->user->email }}). Amount {{ $subscription->plan?->amountLabel() ?? 'not priced' }}. No card payment is collected. Resulting state: Active.">@csrf <button class="text-sm text-lamu-800">Activate</button></form>
                        @endif
                        @if ($subscription->status === \App\Enums\SubscriptionStatus::Active)
                            <form method="POST" action="{{ route('staff.memberships.renew', $subscription) }}" data-confirm="Renew {{ $subscription->plan?->name ?? 'Investor Pass' }} for {{ $subscription->user->name }}. Amount {{ $subscription->plan?->amountLabel() ?? 'not priced' }}. Resulting state: Active for a new period. This does not collect a provider payment.">@csrf <button class="text-sm text-lamu-800">Renew</button></form>
                            <form method="POST" action="{{ route('staff.memberships.cancel', $subscription) }}" data-confirm="Cancel membership for {{ $subscription->user->name }} ({{ $subscription->user->email }}). Resulting state: Cancelled.">@csrf <button class="text-sm text-lamu-800">Cancel</button></form>
                            <form method="POST" action="{{ route('staff.memberships.credit', $subscription) }}" data-confirm="Authorize a service credit for {{ $subscription->user->name }}. This is not a payment. Resulting state: credit days added to the active membership." class="mt-2 flex flex-wrap gap-2">
                                @csrf
                                <input type="number" name="days" min="1" max="90" required placeholder="Days" class="w-20 rounded border border-sand-200 px-2 py-1 text-sm">
                                <input name="reason" required placeholder="Reason" class="rounded border border-sand-200 px-2 py-1 text-sm">
                                <button class="text-sm text-lamu-800">Authorize credit</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No membership requests" message="Buyer and broker accounts can request a pass. A new investor user is not created." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
