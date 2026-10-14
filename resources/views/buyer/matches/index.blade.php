<x-workspace-layout heading="My property matches">
    <p class="mt-3 max-w-3xl text-stone-700">Properties a member of staff has approved for you. Suitability notes come from the search that found the land.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Fit', 'Status']">
            @forelse ($matches as $match)
                @php
                    $fit = $match->sourcingRequest?->matches?->firstWhere('property_id', $match->property_id);
                @endphp
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('buyer.matches.show', $match) }}" class="font-medium text-lamu-900 hover:underline">{{ $match->property->title }}</a>
                        <p class="text-xs text-stone-500">{{ $match->property->locationLabel() }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm text-stone-600">
                        {{ $fit?->band?->label() ?? 'Approved' }}
                        @if ($fit && filled($fit->reasons))
                            <span class="block text-xs">{{ implode(' ', $fit->reasons) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $match->status->label() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8">
                        <x-workspace.empty title="No approved matches" message="A match appears here after staff approve it. You can keep browsing or record a new search." action="Find land for me" :href="route('buyer.sourcing.create')" />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$matches" /></div>
    </div>
</x-workspace-layout>
