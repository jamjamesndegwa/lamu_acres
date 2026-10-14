<x-workspace-layout heading="Commission settlement">
    <h2 class="mt-6 font-semibold text-lamu-900">Earned, waiting for finance approval</h2>
    <div class="mt-3 space-y-3">
        @forelse ($earned as $agreement)
            <form method="POST" action="{{ route('staff.settlements.approve', $agreement) }}" data-turbo-frame="_top" data-confirm="Approve {{ $agreement->reference }} of KSh {{ number_format((int) $agreement->earned_amount) }} payable to {{ $agreement->broker->name }}. Resulting state: Payable. This does not pay the broker." class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                @csrf
                <p>{{ $agreement->reference }} · KSh {{ number_format((int) $agreement->earned_amount) }} · {{ $agreement->broker->name }}</p>
                <button class="mt-2 rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Approve as payable</button>
            </form>
        @empty
            <x-workspace.empty title="No earned commissions" message="Earned commission appears after a completed sale meets the agreement." />
        @endforelse
    </div>
    <h2 class="mt-8 font-semibold text-lamu-900">Payable</h2>
    <div class="mt-3 space-y-3">
        @forelse ($payable as $agreement)
            <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p>{{ $agreement->reference }} · KSh {{ number_format((int) $agreement->earned_amount) }}</p>
                @if ($agreement->payment)
                    <p class="mt-1">Payment {{ $agreement->payment->reference }} · {{ $agreement->payment->status }} · {{ $agreement->payment->reconciliation->label() }}</p>
                    @if ($agreement->payment->status === 'initiated')
                        <form method="POST" action="{{ route('staff.settlements.pay', $agreement) }}" data-turbo-frame="_top" data-confirm="Record payment {{ $agreement->payment->reference }} of KSh {{ number_format((int) $agreement->payment->amount) }} to {{ $agreement->broker->name }} as paid. Resulting state: Paid. Do this only when the evidence matches. A pending provider result is not a successful payment." class="mt-2 flex gap-2">
                            @csrf
                            <select name="reconciliation" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                                <option value="matched">Matched</option>
                                <option value="unmatched">Unmatched</option>
                                <option value="exception">Exception</option>
                            </select>
                            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Record paid</button>
                        </form>
                    @endif
                @else
                    <form method="POST" action="{{ route('staff.settlements.initiate', $agreement) }}" data-turbo-frame="_top" data-confirm="Initiate KSh {{ number_format((int) $agreement->earned_amount) }} to {{ $agreement->broker->name }} for {{ $agreement->reference }}. Resulting state: Initiated, not paid. Starting payment processing does not mark it successful." class="mt-2 flex flex-wrap gap-2">
                        @csrf
                        <input name="reference" required placeholder="Payment reference" class="rounded-lg border border-sand-200 px-3 py-2">
                        <select name="reconciliation" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                            <option value="unmatched">Unmatched</option>
                            <option value="matched">Matched</option>
                            <option value="exception">Exception</option>
                        </select>
                        <button class="rounded-full border border-sand-200 px-4 py-2">Initiate payment</button>
                    </form>
                @endif
            </article>
        @empty
            <x-workspace.empty title="No payables" message="Finance approval moves an earned commission here." />
        @endforelse
    </div>
    <h2 class="mt-8 font-semibold text-lamu-900">Payment history</h2>
    <div class="mt-3">
        <x-workspace.table :columns="['Reference', 'Status', 'Reconciliation', 'Amount']">
            @forelse ($payments as $payment)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm">{{ $payment->reference }}</td>
                    <td class="px-4 py-4 text-sm">{{ $payment->status }}</td>
                    <td class="px-4 py-4 text-sm">{{ $payment->reconciliation->label() }}</td>
                    <td class="px-4 py-4 text-sm">KSh {{ number_format($payment->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No payments" message="Initiated and paid commissions are listed here." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
