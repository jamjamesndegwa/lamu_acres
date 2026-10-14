<x-workspace-layout heading="My properties">
    <p class="mt-3 max-w-3xl text-stone-700">Only land with a verified ownership or authorised relationship is listed here.</p>
    <a href="{{ route('seller.properties.create') }}" class="mt-4 inline-block rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit property</a>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Reference', 'Status']">
            @forelse ($properties as $property)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('seller.properties.show', $property) }}">{{ $property->title }}</a></td>
                    <td class="px-4 py-4 text-sm">{{ $property->reference }}</td>
                    <td class="px-4 py-4 text-sm">{{ $property->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No verified properties" message="A property appears here after staff verify your relationship to it." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
