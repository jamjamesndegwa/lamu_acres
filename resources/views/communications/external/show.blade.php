<x-workspace-layout heading="Communication details">
    <div class="mt-4">
        <a href="{{ route($indexRoute) }}" class="text-sm font-medium text-lamu-900 underline">← Back to register</a>
    </div>

    @if ($stalledQueueWarning)
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $stalledQueueWarning }}
        </div>
    @endif

    <article class="mt-6 space-y-4 rounded-2xl border border-sand-200 bg-white p-6 text-sm">
        <p><span class="font-medium text-lamu-900">Reference</span> #{{ $delivery->id }}</p>
        <p><span class="font-medium text-lamu-900">Channel</span> {{ $delivery->channel->label() }}</p>
        <p><span class="font-medium text-lamu-900">Generated</span> {{ $delivery->created_at?->timezone(config('app.timezone'))->toDayDateTimeString() }}</p>
        <p><span class="font-medium text-lamu-900">Recipient</span> {{ $delivery->recipient?->name ?? '—' }} ({{ $delivery->destination }})</p>
        <p><span class="font-medium text-lamu-900">Subject</span> {{ $delivery->subject }}</p>
        <p><span class="font-medium text-lamu-900">Event</span> {{ $delivery->event->label() }}</p>
        <p><span class="font-medium text-lamu-900">Status</span> {{ ucfirst(str_replace('_', ' ', $delivery->status)) }}</p>
        <p><span class="font-medium text-lamu-900">Queue</span> {{ $delivery->queue_name ?? '—' }}</p>
        <p><span class="font-medium text-lamu-900">Attempts</span> {{ $delivery->attempts }}</p>
        <p><span class="font-medium text-lamu-900">Queued at</span> {{ optional($delivery->queued_at)->timezone(config('app.timezone'))?->toDayDateTimeString() ?? '—' }}</p>
        <p><span class="font-medium text-lamu-900">Last attempt</span> {{ optional($delivery->last_attempt_at)->timezone(config('app.timezone'))?->toDayDateTimeString() ?? '—' }}</p>
        <p><span class="font-medium text-lamu-900">Sent at</span> {{ optional($delivery->sent_at)->timezone(config('app.timezone'))?->toDayDateTimeString() ?? '—' }}</p>
        @if ($delivery->provider_reference)
            <p><span class="font-medium text-lamu-900">Provider reference</span> {{ $delivery->provider_reference }}</p>
        @endif
        @if ($delivery->error)
            <p><span class="font-medium text-lamu-900">Last outcome</span> {{ $delivery->error }}</p>
        @endif
        @if ($delivery->campaign)
            <p><span class="font-medium text-lamu-900">Campaign</span> #{{ $delivery->campaign->id }} — {{ $delivery->campaign->subject }}</p>
        @endif
        @if ($related = $register->relatedUrl($delivery))
            <p><a href="{{ $related }}" class="font-medium text-lamu-900 underline">Open related campaign</a></p>
        @endif
        <div>
            <p class="font-medium text-lamu-900">Message</p>
            <pre class="mt-2 whitespace-pre-wrap rounded-xl bg-sand-50 p-4 text-stone-800">{{ $register->displayBody($delivery) }}</pre>
        </div>
    </article>
</x-workspace-layout>
