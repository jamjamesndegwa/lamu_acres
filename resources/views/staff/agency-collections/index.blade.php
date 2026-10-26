<x-workspace-layout heading="Agency fee collections">
    <h2 class="mt-6 font-semibold text-lamu-900">Receivable</h2>
    <div class="mt-3 space-y-3">
        @forelse ($receivable as $agreement)
            <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p>{{ $agreement->reference }} · Earned KSh {{ number_format((int) $agreement->earned_amount) }} · Outstanding KSh {{ number_format($agreement->outstandingReceivable()) }}</p>
                <form method="POST" action="{{ route('staff.agency-collections.initiate', $agreement) }}" data-turbo-frame="_top" class="mt-2 flex flex-wrap gap-2">
                    @csrf
                    <input name="reference" required placeholder="Receipt reference" class="rounded-lg border border-sand-200 px-3 py-2">
                    <input name="amount" type="number" min="1" max="{{ $agreement->outstandingReceivable() }}" required placeholder="Amount" class="rounded-lg border border-sand-200 px-3 py-2">
                    <select name="reconciliation" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="unmatched">Unmatched</option>
                        <option value="matched">Matched</option>
                    </select>
                    <button class="rounded-full border border-sand-200 px-4 py-2">Initiate collection</button>
                </form>
            </article>
        @empty
            <x-workspace.empty title="No receivables" message="Earned agency fees appear after sale completion." />
        @endforelse
    </div>
    <h2 class="mt-8 font-semibold text-lamu-900">Receipt history</h2>
    <div class="mt-3">
        <x-workspace.table :columns="['Reference', 'Status', 'Amount', 'Action']">
            @forelse ($receipts as $receipt)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm">{{ $receipt->reference }}</td>
                    <td class="px-4 py-4 text-sm">{{ $receipt->status }}</td>
                    <td class="px-4 py-4 text-sm">KSh {{ number_format($receipt->amount) }}</td>
                    <td class="px-4 py-4 text-sm">
                        @if ($receipt->status === 'initiated')
                            <form method="POST" action="{{ route('staff.agency-collections.collect', $receipt) }}" data-turbo-frame="_top" data-confirm="Record this collection as received?">
                                @csrf
                                <input type="hidden" name="reconciliation" value="matched">
                                <button class="text-lamu-800 underline">Record collected</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No receipts" /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
