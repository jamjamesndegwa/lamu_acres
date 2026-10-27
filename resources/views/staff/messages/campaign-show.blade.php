<x-workspace-layout :heading="$campaign->subject">
    @if (session('status'))
        <p class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('status') }}</p>
    @endif
    <div class="mt-6 rounded-2xl border border-sand-200 bg-white p-4 text-sm">
        <p class="font-medium text-lamu-900">{{ $campaign->channel->label() }} · {{ $campaign->status->label() }}</p>
        <p class="mt-2 whitespace-pre-wrap text-stone-700">{{ $campaign->body }}</p>
        <p class="mt-3 text-stone-600">Recipients: {{ $campaign->recipient_count }} · Sent: {{ $campaign->sent_count }} · Failed: {{ $campaign->failed_count }}</p>
        @if ($campaign->status === \App\Enums\MessageCampaignStatus::Draft)
            <form method="POST" action="{{ route('staff.messages.campaigns.queue', $campaign) }}" data-turbo-frame="_top" data-confirm="Queue this campaign for delivery?" class="mt-4">
                @csrf
                <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Queue delivery</button>
            </form>
        @endif
    </div>
    <h2 class="mt-8 text-lg font-semibold text-lamu-900">Recent deliveries</h2>
    <div class="mt-4 space-y-2">
        @forelse ($campaign->deliveries as $delivery)
            <article class="rounded-xl border border-sand-200 bg-white p-3 text-sm">
                <p>{{ $delivery->recipient?->name }} · {{ $delivery->status }}@if ($delivery->provider_reference) · ref {{ $delivery->provider_reference }}@endif</p>
            </article>
        @empty
            <p class="text-sm text-stone-600">Deliveries appear after the campaign is queued.</p>
        @endforelse
    </div>
</x-workspace-layout>
