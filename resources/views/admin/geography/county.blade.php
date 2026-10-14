<x-workspace-layout :heading="$county->name">
    <div class="mt-4 flex flex-wrap justify-between gap-3">
        <a href="{{ route('admin.geography.index') }}" class="text-sm font-medium text-lamu-800">All counties</a>
        <a href="{{ route('admin.geography.localities.create', $county) }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Add place</a>
    </div>
    <p class="mt-3 text-sm text-stone-600">Boundaries stay empty unless a verified geometry file has been stored. Named points are approximate.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Place', 'Kind', 'Parent', 'Point', 'Boundary', '']">
            @foreach ($localities as $locality)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <span class="font-medium text-lamu-900">{{ $locality->name }}</span>
                        @if ($locality->aliases->isNotEmpty())
                            <span class="mt-1 block text-xs text-stone-500">{{ $locality->aliases->pluck('name')->join(', ') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $locality->kind->label() }}</td>
                    <td class="px-4 py-4 text-sm">{{ $locality->parent->name ?? '—' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $locality->latitude ? 'Named point' : 'Unset' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $locality->hasBoundary() ? 'Set' : 'Unset' }}</td>
                    <td class="px-4 py-4 text-right">
                        <a href="{{ route('admin.geography.localities.edit', $locality) }}" data-turbo-frame="modal" class="text-sm font-medium text-lamu-800">Edit</a>
                    </td>
                </tr>
            @endforeach
        </x-workspace.table>
        <div class="mt-4">{{ $localities->links() }}</div>
    </div>
</x-workspace-layout>
