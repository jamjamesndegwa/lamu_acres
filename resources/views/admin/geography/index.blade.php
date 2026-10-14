<x-workspace-layout heading="Counties and localities">
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.geography.index') }}" class="flex min-w-0 flex-1 gap-2">
            <label class="sr-only" for="geo-q">Search places</label>
            <input id="geo-q" name="q" value="{{ request('q') }}" placeholder="Name or alias" class="w-full max-w-sm rounded-lg border border-sand-200 px-3 py-2">
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Search</button>
        </form>
        <a href="{{ route('admin.geography.counties.create') }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Add county</a>
    </div>

    @if ($matches->isNotEmpty())
        <ul class="mt-4 space-y-2 text-sm">
            @foreach ($matches as $match)
                <li class="rounded-xl border border-sand-200 bg-white px-4 py-3">
                    {{ $match->name }}
                    <span class="text-stone-500">· {{ $match->county->name }} · {{ $match->kind->label() }}</span>
                    @unless ($match->hasBoundary())
                        <span class="text-stone-500">· boundary not set</span>
                    @endunless
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-6">
        <x-workspace.table :columns="['County', 'Places', 'Active', '']">
            @foreach ($counties as $county)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 font-medium text-lamu-900">{{ $county->name }}</td>
                    <td class="px-4 py-4">{{ $county->localities_count }}</td>
                    <td class="px-4 py-4">{{ $county->is_active ? 'Active' : 'Inactive' }}</td>
                    <td class="px-4 py-4 text-right">
                        <a href="{{ route('admin.geography.counties.show', $county) }}" class="text-sm font-medium text-lamu-800">Open</a>
                    </td>
                </tr>
            @endforeach
        </x-workspace.table>
        <div class="mt-4">{{ $counties->links() }}</div>
    </div>
</x-workspace-layout>
