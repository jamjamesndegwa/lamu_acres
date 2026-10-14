<x-workspace-layout heading="Buyer requirements board">
    <p class="mt-3 max-w-3xl text-stone-700">Open buyer searches. Names and contact details are not shown. A candidate is only added when the recorded requirement accepts one of your published listings. Staff still decide what the buyer sees.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Area', 'Size', 'Budget', 'Use', 'Status']">
            @forelse ($requests as $requirement)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('broker.requirements.show', $requirement) }}" class="font-medium text-lamu-900 hover:underline">{{ $requirement->reference }}</a>
                    </td>
                    <td class="px-4 py-4">{{ $requirement->location_label }}</td>
                    <td class="px-4 py-4">{{ $requirement->min_acreage ?: '—' }} – {{ $requirement->max_acreage ?: '—' }} acres</td>
                    <td class="px-4 py-4">{{ $requirement->maximum_budget ? 'KSh '.number_format($requirement->maximum_budget) : 'Not stated' }}</td>
                    <td class="px-4 py-4">{{ $requirement->intended_use?->label() ?? 'Not stated' }}</td>
                    <td class="px-4 py-4">{{ $requirement->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8"><x-workspace.empty title="No open requirements" message="Buyer searches will appear here without the buyer’s identity." /></td></tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$requests" /></div>
    </div>
</x-workspace-layout>
