<x-workspace-layout heading="Sourcing queue">
    <p class="mt-3 max-w-3xl text-stone-700">Buyer searches waiting for a sourcing officer.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Buyer', 'Status', 'Matches', 'Assignee']">
            @forelse ($requests as $sourcingRequest)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('staff.sourcing.show', $sourcingRequest) }}" class="font-medium text-lamu-900 hover:underline">{{ $sourcingRequest->reference }}</a>
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $sourcingRequest->buyer->name }}</td>
                    <td class="px-4 py-4 text-sm">{{ $sourcingRequest->status->label() }}</td>
                    <td class="px-4 py-4 text-sm">{{ $sourcingRequest->matches_count }}</td>
                    <td class="px-4 py-4 text-sm">{{ $sourcingRequest->assignee->name ?? 'Unassigned' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8">
                        <x-workspace.empty title="Queue is clear" message="New buyer searches will appear here." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$requests" /></div>
    </div>
</x-workspace-layout>
