<x-workspace-layout heading="Transactions">
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Property', 'Status']">
            @forelse ($transactions as $transaction)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('seller.transactions.show', $transaction) }}">{{ $transaction->reference }}</a></td>
                    <td class="px-4 py-4 text-sm">{{ $transaction->property->title }}</td>
                    <td class="px-4 py-4 text-sm">{{ $transaction->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No transactions" message="Statements appear when staff record them." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
