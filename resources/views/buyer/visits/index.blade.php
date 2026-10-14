<x-workspace-layout heading="Site visits">
    <p class="mt-3 max-w-3xl text-stone-700">Visits you have requested on approved matches.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Status', 'Requested for']">
            @forelse ($visits as $visit)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm">
                        <a href="{{ route('buyer.matches.show', $visit->property_match_id) }}" class="font-medium text-lamu-900 hover:underline">{{ $visit->property->title }}</a>
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $visit->status->label() }}</td>
                    <td class="px-4 py-4 text-sm">{{ $visit->requested_for->toDayDateTimeString() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8"><x-workspace.empty title="No visits" message="Request a visit from an approved match." /></td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$visits" /></div>
    </div>
</x-workspace-layout>
