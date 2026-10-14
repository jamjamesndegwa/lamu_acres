<x-workspace-layout heading="Calendar">
    <p class="mt-3 max-w-3xl text-stone-700">Upcoming site visits and follow-ups.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['When', 'Property', 'Buyer', 'Status']">
            @forelse ($visits as $visit)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm">{{ ($visit->confirmed_for ?? $visit->requested_for)?->toDayDateTimeString() }}</td>
                    <td class="px-4 py-4">{{ $visit->property->title }}</td>
                    <td class="px-4 py-4 text-sm">{{ $visit->buyer->name }}</td>
                    <td class="px-4 py-4 text-sm">{{ $visit->status->label() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8">
                        <x-workspace.empty title="Nothing scheduled" message="Confirmed and requested site visits will appear here." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
