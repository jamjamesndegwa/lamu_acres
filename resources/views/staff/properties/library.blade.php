<x-workspace-layout heading="Property media">
    <p class="mt-3 max-w-3xl text-stone-700">Photos and videos attached to properties in the register.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Files']">
            @forelse ($properties as $property)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.properties.media', $property) }}">{{ $property->title }}</a>
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $property->media_count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-4 py-8">
                        <x-workspace.empty title="No properties" message="Property media appears here after a property is on the register." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4">{{ $properties->links() }}</div>
    </div>
</x-workspace-layout>
