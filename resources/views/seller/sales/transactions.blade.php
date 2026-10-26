<x-workspace-layout heading="Sales records">
    <section class="mt-6">
        <h2 class="text-lg font-medium text-lamu-900">Completed sales</h2>
        <p class="mt-1 text-sm text-stone-600">Authoritative records from staff closing. These reflect agreed price, acreage and completion date.</p>
        <div class="mt-4">
            <x-workspace.table :columns="['Reference', 'Property', 'Acreage sold', 'Agreed price', 'Completed']">
                @forelse ($completedSales as $sale)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('seller.sales.show', $sale) }}">{{ $sale->reference }}</a></td>
                        <td class="px-4 py-4 text-sm">{{ $sale->property->title }}</td>
                        <td class="px-4 py-4 text-sm">{{ number_format((float) $sale->acreage, 2) }} acres</td>
                        <td class="px-4 py-4 text-sm">KSh {{ number_format((int) $sale->agreed_price) }}</td>
                        <td class="px-4 py-4 text-sm">{{ $sale->completed_at?->timezone(config('app.timezone'))->format('j M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8"><x-workspace.empty title="No completed sales yet" message="Completed sales appear here after staff close the transaction." /></td></tr>
                @endforelse
            </x-workspace.table>
            @if ($completedSales->hasPages())
                <div class="mt-4">{{ $completedSales->links() }}</div>
            @endif
        </div>
    </section>

    @if ($legacyTransactions->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-medium text-lamu-900">Historical statements</h2>
            <p class="mt-1 text-sm text-stone-600">Older staff-recorded statements kept for reference. They may not include every completed sale above.</p>
            <div class="mt-4">
                <x-workspace.table :columns="['Reference', 'Property', 'Status']">
                    @foreach ($legacyTransactions as $transaction)
                        <tr class="border-b border-sand-200 last:border-b-0">
                            <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('seller.transactions.show', $transaction) }}">{{ $transaction->reference }}</a></td>
                            <td class="px-4 py-4 text-sm">{{ $transaction->property->title }}</td>
                            <td class="px-4 py-4 text-sm"><x-status-badge :label="$transaction->status->label()" /></td>
                        </tr>
                    @endforeach
                </x-workspace.table>
                @if ($legacyTransactions->hasPages())
                    <div class="mt-4">{{ $legacyTransactions->links() }}</div>
                @endif
            </div>
        </section>
    @endif
</x-workspace-layout>
