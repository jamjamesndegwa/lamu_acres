<x-workspace-layout heading="Matches">
    <p class="mt-3 max-w-3xl text-stone-700">Properties a member of staff has approved for you.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Status']">
            @forelse ($matches as $match)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('buyer.matches.show', $match) }}" class="font-medium text-lamu-900 hover:underline">{{ $match->property->title }}</a>
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $match->status->label() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-4 py-8">
                        <x-workspace.empty title="No approved matches" message="A match appears here after staff approve it." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$matches" /></div>
    </div>
</x-workspace-layout>
