<x-workspace-layout heading="Saved Properties">
    <p class="mt-3 max-w-3xl text-stone-700">Land you have kept to review. Only properties saved on this account are listed.</p>

    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Location', '']">
            @forelse ($saved as $item)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ $item->property->publicUrl() }}" class="font-medium text-lamu-900 hover:underline">{{ $item->property->title }}</a>
                        <p class="text-xs text-stone-500">{{ $item->property->reference }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm text-stone-600">{{ $item->property->locationLabel() }}</td>
                    <td class="px-4 py-4 text-right">
                        <form method="POST" action="{{ route('buyer.saved.destroy', $item->property) }}" data-confirm="Remove this saved property?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-lamu-800">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8">
                        <x-workspace.empty title="No saved properties" message="Save a published property from its page. It will be listed here." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4">
            <x-workspace.pagination :paginator="$saved" />
        </div>
    </div>
</x-workspace-layout>
