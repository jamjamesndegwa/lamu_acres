<x-workspace-layout heading="Listing review">
    <p class="mt-3 max-w-3xl text-stone-700">Broker submissions that are still unpublished. A returned row is waiting on the broker. Approving one places it on the public directory.</p>
    <div class="mt-4 flex gap-2 text-sm">
        <a href="{{ route('staff.listings.index') }}" @class(['rounded-full px-4 py-2', 'bg-lamu-900 text-white' => $queue === 'all', 'border border-sand-200' => $queue !== 'all'])>All</a>
        <a href="{{ route('staff.listings.index', ['queue' => 'pending']) }}" @class(['rounded-full px-4 py-2', 'bg-lamu-900 text-white' => $queue === 'pending', 'border border-sand-200' => $queue !== 'pending'])>Pending</a>
        <a href="{{ route('staff.listings.index', ['queue' => 'returned']) }}" @class(['rounded-full px-4 py-2', 'bg-lamu-900 text-white' => $queue === 'returned', 'border border-sand-200' => $queue !== 'returned'])>Returned</a>
    </div>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Broker', 'Queue', 'Status']">
            @forelse ($listings as $listing)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('staff.listings.show', $listing) }}" class="font-medium text-lamu-900 hover:underline">{{ $listing->title }}</a>
                    </td>
                    <td class="px-4 py-4">{{ $listing->submitter?->name }}</td>
                    <td class="px-4 py-4"><x-status-badge :label="$listing->returned ? 'Returned' : 'Pending'" /></td>
                    <td class="px-4 py-4"><x-status-badge :label="$listing->status->label()" /></td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No broker submissions" message="New submissions will be listed here." /></td></tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$listings" /></div>
    </div>
</x-workspace-layout>
