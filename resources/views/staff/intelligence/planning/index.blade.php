<x-workspace-layout heading="Planning and zoning">
    <div class="mt-4 flex justify-end"><a href="{{ route('staff.planning.create') }}" class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">New record</a></div>
    <div class="mt-6">
        <x-workspace.table :columns="['Record', 'Area', 'Publication']">
            @forelse ($records as $record)
                <tr class="border-b border-sand-200"><td class="px-4 py-3"><a class="text-lamu-800 hover:underline" href="{{ route('staff.planning.show', $record) }}">{{ $record->title }}</a></td><td class="px-4 py-3">{{ $record->area }}</td><td class="px-4 py-3">{{ $record->publication_status->label() }}</td></tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No planning records" message="Source documents will be listed here after a researcher enters them." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
