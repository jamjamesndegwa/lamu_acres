<x-workspace-layout heading="Map layers">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Each layer draws only geometry that is already stored. A county or place with no verified boundary stays off the map, and a named point is not treated as a border.</p>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.geography.layers.index') }}" class="flex min-w-0 flex-1 flex-wrap gap-2">
            <label class="sr-only" for="layer-q">Search layers</label>
            <input id="layer-q" name="q" value="{{ $term }}" placeholder="Layer, source, or place" class="w-full max-w-sm rounded-lg border border-sand-200 px-3 py-2">
            <label class="sr-only" for="layer-kind">Kind</label>
            <select id="layer-kind" name="kind" class="rounded-lg border border-sand-200 bg-white px-3 py-2 text-sm">
                <option value="">All kinds</option>
                @foreach (\App\Enums\MapLayerKind::cases() as $option)
                    <option value="{{ $option->value }}" @selected($kind === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
        </form>
        <a href="{{ route('admin.geography.layers.create') }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Add layer</a>
    </div>

    <div class="mt-6">
        <x-workspace.table :columns="['Layer', 'Kind', 'Source', 'On the map', 'Missing geometry', 'Shown', '']">
            @forelse ($layers as $layer)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <p class="font-medium text-lamu-900">{{ $layer->name }}</p>
                        @if ($layer->county)
                            <p class="text-xs text-stone-500">Limited to {{ $layer->county->name }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $layer->kind->label() }}</td>
                    <td class="px-4 py-4 text-sm">{{ $layer->source }}</td>
                    <td class="px-4 py-4 text-sm">{{ $layer->drawn_count }}</td>
                    <td class="px-4 py-4 text-sm">{{ $layer->missing_count }}</td>
                    <td class="px-4 py-4 text-sm">{{ $layer->is_visible ? 'Yes' : 'Hidden' }}</td>
                    <td class="px-4 py-4 text-right">
                        <a href="{{ route('admin.geography.layers.edit', $layer) }}" data-turbo-frame="modal" class="text-sm font-medium text-lamu-800">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8">
                        <x-workspace.empty title="No layers match" message="Try another name, or add a layer that reads stored records." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
    </div>

    @include('admin.geography.partials.map', ['features' => $features])

    @if ($gaps !== [])
        <section class="mt-8" aria-labelledby="missing-geometry">
            <h2 id="missing-geometry" class="font-semibold text-lamu-900">Geometry still missing</h2>
            <p class="mt-2 max-w-3xl text-sm text-stone-600">These records are in a visible layer, and nothing is drawn for them.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($gaps as $gap)
                    <article class="rounded-2xl border border-sand-200 bg-white p-4">
                        <h3 class="font-medium text-lamu-900">{{ $gap['layer'] }}</h3>
                        <ul class="mt-3 space-y-2 text-sm text-stone-700">
                            @foreach ($gap['items'] as $item)
                                <li>{{ $item['name'] }} · {{ $item['detail'] }}</li>
                            @endforeach
                        </ul>
                        @if ($gap['more'] > 0)
                            <p class="mt-3 text-xs text-stone-500">{{ $gap['more'] }} more are also missing geometry.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</x-workspace-layout>
