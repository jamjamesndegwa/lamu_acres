<x-workspace-layout :heading="$county->name">
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.geography.index') }}" class="text-sm font-medium text-lamu-800">All counties</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.geography.counties.edit', $county) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Edit county</a>
            <a href="{{ route('admin.geography.localities.create', $county) }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Add place</a>
        </div>
    </div>

    <section class="mt-6" aria-labelledby="county-record">
        <h2 id="county-record" class="font-semibold text-lamu-900">County record</h2>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-sand-200 bg-white p-4">
                <dt class="text-stone-500">Code</dt>
                <dd class="mt-1 font-medium text-lamu-900">{{ $county->code ?: 'Not set' }}</dd>
            </div>
            <div class="rounded-2xl border border-sand-200 bg-white p-4">
                <dt class="text-stone-500">Status</dt>
                <dd class="mt-1 font-medium text-lamu-900">{{ $county->is_active ? 'Active' : 'Inactive' }}</dd>
            </div>
            <div class="rounded-2xl border border-sand-200 bg-white p-4">
                <dt class="text-stone-500">Named point</dt>
                <dd class="mt-1 font-medium text-lamu-900">{{ $county->hasPoint() ? $county->latitude.', '.$county->longitude : 'Not stored' }}</dd>
            </div>
            <div class="rounded-2xl border border-sand-200 bg-white p-4">
                <dt class="text-stone-500">Verified boundary</dt>
                <dd class="mt-1 font-medium text-lamu-900">{{ $county->hasBoundary() ? 'Stored' : 'Not stored' }}</dd>
            </div>
        </dl>
    </section>

    <section class="mt-8" aria-labelledby="place-hierarchy">
        <h2 id="place-hierarchy" class="font-semibold text-lamu-900">Administrative hierarchy</h2>
        <p class="mt-2 max-w-3xl text-sm text-stone-600">Sub-counties, wards, and recognized places stay in the parent relationship that was recorded. A missing boundary is shown as not stored.</p>
        @php
            $roots = $places->whereNull('parent_id');
        @endphp
        @if ($roots->isEmpty())
            <p class="mt-3 text-sm text-stone-600">No places are recorded in this county yet.</p>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($roots as $root)
                    @include('admin.geography.partials.place-branch', ['place' => $root, 'places' => $places])
                @endforeach
            </ul>
        @endif
    </section>

    @include('admin.geography.partials.map', ['features' => $mapFeatures])

    <section class="mt-8" aria-labelledby="locality-register">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="locality-register" class="font-semibold text-lamu-900">Localities</h2>
            <x-filter-bar :action="route('admin.geography.counties.show', $county)" class="flex flex-wrap gap-2">
                <label class="sr-only" for="place-q">Filter places</label>
                <input id="place-q" name="q" value="{{ $term }}" placeholder="Name or alias" class="rounded-lg border border-sand-200 px-3 py-2 text-sm">
                <label class="sr-only" for="place-kind">Kind</label>
                <select id="place-kind" name="kind" class="rounded-lg border border-sand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All kinds</option>
                    @foreach (\App\Enums\LocalityKind::cases() as $option)
                        <option value="{{ $option->value }}" @selected($kind === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
            </x-filter-bar>
        </div>
        <div class="mt-4">
            <x-workspace.table :columns="['Place', 'Kind', 'Parent', 'Aliases', 'Point', 'Boundary', 'Listings', '']">
                @forelse ($localities as $locality)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4 font-medium text-lamu-900">{{ $locality->name }}</td>
                        <td class="px-4 py-4 text-sm">{{ $locality->kind->label() }}</td>
                        <td class="px-4 py-4 text-sm">{{ $locality->parent->name ?? '—' }}</td>
                        <td class="px-4 py-4 text-sm">{{ $locality->aliases->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-4 text-sm">{{ $locality->hasPoint() ? 'Stored' : 'Not stored' }}</td>
                        <td class="px-4 py-4 text-sm">{{ $locality->hasBoundary() ? 'Stored' : 'Not stored' }}</td>
                        <td class="px-4 py-4 text-sm">{{ $locality->listings_count }}</td>
                        <td class="px-4 py-4 text-right">
                            <a href="{{ route('admin.geography.localities.edit', $locality) }}" data-turbo-frame="modal" class="text-sm font-medium text-lamu-800">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8">
                            <x-workspace.empty title="No places in this filter" message="Clear the filter or add a sub-county, ward, or recognized place." />
                        </td>
                    </tr>
                @endforelse
            </x-workspace.table>
        </div>
    </section>
</x-workspace-layout>
