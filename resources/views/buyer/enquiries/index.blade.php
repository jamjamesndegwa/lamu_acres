<x-workspace-layout heading="My Enquiries">
    <p class="mt-3 max-w-3xl text-stone-700">Questions you have sent about specific properties, and the replies that were shared with you.</p>

    @if ($enquiries->isNotEmpty())
        <x-buyer.next-step
            label="Open the conversation"
            :href="route('buyer.enquiries.show', $enquiries->first())"
            detail="Read the replies, then book a visit or make an offer from an approved match."
        />
    @endif

    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Status', 'Sent']">
            @forelse ($enquiries as $enquiry)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('buyer.enquiries.show', $enquiry) }}" class="font-medium text-lamu-900 hover:underline">{{ $enquiry->property->title }}</a>
                        <p class="text-xs text-stone-500">{{ $enquiry->property->reference }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $enquiry->status->label() }}</td>
                    <td class="px-4 py-4 text-sm text-stone-600">{{ $enquiry->created_at->toFormattedDateString() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8">
                        <x-workspace.empty title="No enquiries yet" message="An enquiry is created when you send a question from a property page." action="Explore land" :href="route('land.index')" />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4">
            <x-workspace.pagination :paginator="$enquiries" />
        </div>
    </div>
</x-workspace-layout>
