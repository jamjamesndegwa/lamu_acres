<x-workspace-layout heading="Infrastructure reference data">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Roads, water, power, jetties, and other references recorded here are the same records shown on property files for that county. A point is stored only when coordinates are entered. A missing boundary is not invented.</p>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.geography.infrastructure.index') }}" class="flex min-w-0 flex-1 flex-wrap gap-2">
            <label class="sr-only" for="infra-q">Search infrastructure</label>
            <input id="infra-q" name="q" value="{{ $term }}" placeholder="Name, source, or place" class="w-full max-w-xs rounded-lg border border-sand-200 px-3 py-2">
            <label class="sr-only" for="infra-kind">Kind</label>
            <select id="infra-kind" name="kind" class="rounded-lg border border-sand-200 bg-white px-3 py-2 text-sm">
                <option value="">All kinds</option>
                @foreach (\App\Enums\InfrastructureKind::cases() as $option)
                    <option value="{{ $option->value }}" @selected($kind === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="infra-county">County</label>
            <select id="infra-county" name="county" class="rounded-lg border border-sand-200 bg-white px-3 py-2 text-sm">
                <option value="">All counties</option>
                @foreach ($counties as $county)
                    <option value="{{ $county->id }}" @selected((string) $countyId === (string) $county->id)>{{ $county->name }}</option>
                @endforeach
            </select>
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
        </form>
        <a href="{{ route('admin.geography.infrastructure.create') }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Add reference</a>
    </div>

    <div class="mt-6">
        <x-workspace.table :columns="['Name', 'Kind', 'County', 'Place', 'Point', 'Boundary', 'Source', '']">
            @forelse ($features as $feature)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <p class="font-medium text-lamu-900">{{ $feature->name }}</p>
                        <p class="text-xs text-stone-500">{{ $feature->is_active ? 'Active' : 'Inactive' }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $feature->kind->label() }}</td>
                    <td class="px-4 py-4 text-sm">{{ $feature->county->name ?? '—' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $feature->locality->name ?? '—' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $feature->hasPoint() ? 'Stored' : 'Not stored' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $feature->hasBoundary() ? 'Stored' : 'Not stored' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $feature->source }}</td>
                    <td class="px-4 py-4 text-right">
                        <a href="{{ route('admin.geography.infrastructure.edit', $feature) }}" data-turbo-frame="modal" class="text-sm font-medium text-lamu-800">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8">
                        <x-workspace.empty title="No infrastructure references" message="Add a road, water point, jetty, or other reference when you have a source. Leave the map blank until coordinates are known." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
    </div>

    @include('admin.geography.partials.map', ['features' => $mapFeatures])
</x-workspace-layout>
