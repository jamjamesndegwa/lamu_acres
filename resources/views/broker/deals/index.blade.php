<x-workspace-layout heading="My deals">
    <p class="mt-3 max-w-3xl text-stone-700">Sales files linked to an introduction staff have accepted. Milestones move only when staff record them. Closing is confirmed by staff.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Deal', 'Introduction', 'Property', 'Stage']">
            @forelse ($deals as $deal)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a href="{{ route('broker.deals.show', $deal) }}" class="font-medium text-lamu-900 hover:underline">{{ $deal->reference }}</a></td>
                    <td class="px-4 py-4">{{ $deal->introduction?->reference }}</td>
                    <td class="px-4 py-4">{{ $deal->property?->title }}</td>
                    <td class="px-4 py-4"><x-status-badge :label="$deal->stage->label()" /></td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No linked deals" message="A deal appears here after staff connect it to an accepted introduction." /></td></tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$deals" /></div>
    </div>
</x-workspace-layout>
