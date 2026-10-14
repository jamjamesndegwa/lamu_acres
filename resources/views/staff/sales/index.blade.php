<x-workspace-layout :heading="$heading">
    @if (! empty($stageCounts ?? null))
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (\App\Enums\SalesStage::cases() as $stage)
                <x-kpi :label="$stage->label()" :value="$stageCounts[$stage->value] ?? 0" />
            @endforeach
        </div>
    @endif
    <a href="{{ route('staff.sales.create') }}" data-turbo-frame="modal" class="mt-4 inline-block rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Create sale</a>
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Property', 'Stage', 'Buyer']">
            @forelse ($transactions as $transaction)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.sales.show', $transaction) }}">{{ $transaction->reference }}</a></td>
                    <td class="px-4 py-4 text-sm">{{ $transaction->property->title }}</td>
                    <td class="px-4 py-4 text-sm"><x-status-badge :label="$transaction->stage->label().($transaction->outcome ? ' · '.$transaction->outcome->label() : '')" /></td>
                    <td class="px-4 py-4 text-sm">{{ $transaction->buyer?->name ?? 'Not linked yet' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No transactions" message="Open a transaction from an enquiry, sourcing request, introduction, or seller instruction." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
