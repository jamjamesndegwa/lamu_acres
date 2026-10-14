<x-workspace-layout heading="Commission agreements">
    <p class="mt-3 max-w-3xl text-stone-700">Terms staff have accepted. A proposed agreement stays with staff until it is accepted. Amounts shown are the figures already recorded.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Agreement', 'Property', 'Status', 'Protection']">
            @forelse ($agreements as $agreement)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a href="{{ route('broker.agreements.show', $agreement) }}" class="font-medium text-lamu-900 hover:underline">{{ $agreement->reference }}</a></td>
                    <td class="px-4 py-4">{{ $agreement->property?->title }}</td>
                    <td class="px-4 py-4"><x-status-badge :label="$agreement->status->label()" /></td>
                    <td class="px-4 py-4">{{ $agreement->protection_starts_at?->toFormattedDateString() ?: 'Not recorded' }} – {{ $agreement->protection_ends_at?->toFormattedDateString() ?: 'Not recorded' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No accepted terms" message="Accepted commission terms will be listed here. Nothing is calculated on this page." /></td></tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$agreements" /></div>
    </div>
</x-workspace-layout>
