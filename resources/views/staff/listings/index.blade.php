<x-workspace-layout heading="Pending listings">
    <p class="mt-3 max-w-3xl text-stone-700">Broker submissions that are still unpublished. Approving one removes it from this queue and places it on the public directory.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Broker', 'Status']">
            @forelse ($listings as $listing)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('staff.listings.show', $listing) }}" class="font-medium text-lamu-900 hover:underline">{{ $listing->title }}</a>
                    </td>
                    <td class="px-4 py-4">{{ $listing->submitter?->name }}</td>
                    <td class="px-4 py-4">{{ $listing->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No broker submissions" message="New submissions will be listed here." /></td></tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$listings" /></div>
    </div>
</x-workspace-layout>
