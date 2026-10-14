<x-workspace-layout :heading="$heading">
    <a href="{{ route('staff.sales.create') }}" class="mt-4 inline-block rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Open transaction</a>
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Property', 'Stage', 'Buyer']">
            @forelse ($transactions as $transaction)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.sales.show', $transaction) }}">{{ $transaction->reference }}</a></td>
                    <td class="px-4 py-4 text-sm">{{ $transaction->property->title }}</td>
                    <td class="px-4 py-4 text-sm">{{ $transaction->stage->label() }}{{ $transaction->outcome ? ' · '.$transaction->outcome->label() : '' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $transaction->buyer?->name ?? 'Not linked yet' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No transactions" message="Open a transaction from an enquiry, sourcing request, introduction, or seller instruction." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
