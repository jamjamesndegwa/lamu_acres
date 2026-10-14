<x-workspace-layout heading="Find Land for Me">
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-stone-700">Tell us where and what you want. Matching uses the map, size, budget, use, and amenities you record.</p>
        <a href="{{ route('buyer.sourcing.create') }}" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">New search</a>
    </div>

    @if ($requests->isNotEmpty())
        <x-buyer.next-step
            label="Open the latest search"
            :href="route('buyer.sourcing.show', $requests->first())"
            detail="Review the criteria, exact matches, and near matches. If nothing fits, explore published land or edit the search."
        />
    @endif

    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Location', 'Status', 'Exact', 'Near', '']">
            @forelse ($requests as $sourcingRequest)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('buyer.sourcing.show', $sourcingRequest) }}" class="font-medium text-lamu-900 hover:underline">{{ $sourcingRequest->reference }}</a>
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $sourcingRequest->location_label ?: 'Map centre' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $sourcingRequest->status->label() }}</td>
                    <td class="px-4 py-4 text-sm">{{ $sourcingRequest->exact_matches_count }}</td>
                    <td class="px-4 py-4 text-sm">{{ $sourcingRequest->near_matches_count }}</td>
                    <td class="px-4 py-4 text-right text-sm">
                        @if ($sourcingRequest->status->value !== 'closed')
                            <a href="{{ route('buyer.sourcing.edit', $sourcingRequest) }}" class="font-medium text-lamu-800">Edit</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8">
                        <x-workspace.empty title="No searches yet" message="No matching land can be found until you record what you want." action="Start a search" :href="route('buyer.sourcing.create')" />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$requests" /></div>
    </div>
</x-workspace-layout>
