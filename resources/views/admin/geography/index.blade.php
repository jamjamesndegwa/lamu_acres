<x-workspace-layout heading="Counties and localities">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Counties and places recorded here are the same records used by property forms, the public location directory, and buyer search.</p>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <x-filter-bar :action="route('admin.geography.index')" class="flex min-w-0 flex-1 gap-2">
            <label class="sr-only" for="geo-q">Search counties</label>
            <input id="geo-q" name="q" value="{{ $term }}" placeholder="County, code, place, or alias" class="w-full max-w-sm rounded-lg border border-sand-200 px-3 py-2">
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Search</button>
        </x-filter-bar>
        <a href="{{ route('admin.geography.counties.create') }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Add county</a>
    </div>

    @if ($matches->isNotEmpty())
        <section class="mt-4" aria-labelledby="place-matches">
            <h2 id="place-matches" class="text-sm font-semibold text-lamu-900">Matching places</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($matches as $match)
                    <li class="rounded-xl border border-sand-200 bg-white px-4 py-3">
                        <a href="{{ route('admin.geography.localities.edit', $match) }}" data-turbo-frame="modal" class="font-medium text-lamu-900 hover:underline">{{ $match->name }}</a>
                        <span class="text-stone-500">· {{ $match->county->name }} · {{ $match->kind->label() }}</span>
                        @if ($match->parent)
                            <span class="text-stone-500">· in {{ $match->parent->name }}</span>
                        @endif
                        <span class="text-stone-500">· {{ $match->hasBoundary() ? 'verified boundary stored' : 'boundary not stored' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-6">
        <x-workspace.table :columns="['County', 'Code', 'Localities', 'Published listings', 'Boundary', '']">
            @forelse ($counties as $county)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('admin.geography.counties.show', $county) }}" class="font-medium text-lamu-900 hover:underline">{{ $county->name }}</a>
                        <p class="text-xs text-stone-500">{{ $county->is_active ? 'Active' : 'Inactive' }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $county->code ?: '—' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $county->localities_count }}</td>
                    <td class="px-4 py-4 text-sm">{{ $county->listings_count }} published</td>
                    <td class="px-4 py-4 text-sm">{{ $county->hasBoundary() ? 'Stored' : 'Not stored' }}</td>
                    <td class="px-4 py-4 text-right text-sm">
                        <a href="{{ route('admin.geography.counties.show', $county) }}" class="font-medium text-lamu-800">Open</a>
                        <a href="{{ route('admin.geography.counties.edit', $county) }}" data-turbo-frame="modal" class="ml-3 font-medium text-lamu-800">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8">
                        <x-workspace.empty title="No counties match" message="Try another name or code, or add the county if it has not been recorded." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4">{{ $counties->links() }}</div>
    </div>
</x-workspace-layout>
