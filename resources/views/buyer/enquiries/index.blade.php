<x-workspace-layout :heading="$purchaseRequestsOnly ? 'My Purchase Requests' : 'My Enquiries'">
    <p class="mt-3 max-w-3xl text-stone-700">{{ $purchaseRequestsOnly ? 'Purchase proposals you have sent and the sales progress shared by Lamu Acres.' : 'Questions and purchase proposals you have sent about specific properties, with replies and progress updates.' }}</p>
    <p class="mt-3 text-sm"><a href="{{ route('buyer.enquiries.index', ['type' => 'purchase']) }}" class="font-medium text-lamu-800 underline">View purchase requests</a> · <a href="{{ route('buyer.enquiries.index') }}" class="font-medium text-lamu-800 underline">View all enquiries</a></p>

    @if ($enquiries->isNotEmpty())
        <x-buyer.next-step
            label="Open your latest request"
            :href="route('buyer.enquiries.show', $enquiries->first())"
            detail="Read replies and track any linked purchase request through the sales process."
        />
    @endif

    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Question', 'Status', 'Sent', 'Last update', '']">
            @forelse ($enquiries as $enquiry)
                @php($latest = $enquiry->responses->first())
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('buyer.enquiries.show', $enquiry) }}" class="font-medium text-lamu-900 hover:underline">{{ $enquiry->property->title }}</a>
                        <p class="text-xs text-stone-500">{{ $enquiry->property->reference }} · {{ $enquiry->property->locationLabel() }}</p>
                        @if ($enquiry->purchase_request)
                            <p class="mt-1 text-xs font-medium text-lamu-800">Purchase request · {{ $enquiry->salesTransaction?->outcome?->label() ?? $enquiry->salesTransaction?->stage?->label() ?? 'Submitted' }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-sm text-stone-700">{{ \Illuminate\Support\Str::limit($enquiry->message, 80) }}</td>
                    <td class="px-4 py-4 text-sm"><x-status-badge :label="$enquiry->status->label()" /></td>
                    <td class="px-4 py-4 text-sm text-stone-600">{{ $enquiry->created_at->toFormattedDateString() }}</td>
                    <td class="px-4 py-4 text-sm text-stone-600">{{ $latest ? $latest->created_at->toFormattedDateString() : 'Awaiting a reply' }}</td>
                    <td class="px-4 py-4 text-right text-sm">
                        <a href="{{ route('buyer.enquiries.show', $enquiry) }}" class="font-medium text-lamu-800">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8">
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
