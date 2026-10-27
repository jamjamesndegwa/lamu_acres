<x-workspace-layout heading="Bulk campaigns">
    <div class="mt-4 flex justify-end">
        <a href="{{ route('staff.messages.campaigns.create') }}" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">New campaign</a>
    </div>
    <div class="mt-6 space-y-3">
        @forelse ($campaigns as $campaign)
            <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p class="font-medium text-lamu-900">{{ $campaign->subject }} · {{ $campaign->channel->label() }} · {{ $campaign->status->label() }}</p>
                <p class="mt-1 text-stone-600">By {{ $campaign->author?->name }} · {{ $campaign->recipient_count }} recipients · {{ $campaign->sent_count }} sent</p>
                <a href="{{ route('staff.messages.campaigns.show', $campaign) }}" class="mt-2 inline-block font-medium text-lamu-800 hover:underline">Open campaign</a>
            </article>
        @empty
            <x-workspace.empty title="No campaigns" message="Create a draft SMS or email campaign for opted-in accounts." />
        @endforelse
    </div>
    <div class="mt-6">{{ $campaigns->links() }}</div>
</x-workspace-layout>
