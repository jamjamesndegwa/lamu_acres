<x-workspace-layout :heading="$enquiry->property->title">
    <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-stone-600">
        {{ $enquiry->property->reference }}
        <x-status-badge :label="$enquiry->status->label()" />
        @if ($enquiry->property->isPubliclyListed())
            <a href="{{ $enquiry->property->publicUrl() }}" class="font-medium text-lamu-800 hover:underline">Open property</a>
        @endif
    </p>
    @if ($enquiry->purchase_request)
        <section class="mt-6 max-w-3xl rounded-2xl border border-lamu-200 bg-lamu-50 p-5">
            <h2 class="font-semibold text-lamu-900">Purchase request progress</h2>
            @if ($enquiry->salesTransaction)
                @php($transaction = $enquiry->salesTransaction)
                <p class="mt-2 text-sm text-lamu-900">{{ $transaction->outcome?->label() ?? $transaction->stage->label() }}</p>
                <dl class="mt-3 grid gap-2 text-sm text-stone-700 sm:grid-cols-2">
                    <div><dt class="font-medium text-lamu-900">Your proposed price</dt><dd>KSh {{ number_format($enquiry->requested_price) }}</dd></div>
                    <div><dt class="font-medium text-lamu-900">Area requested</dt><dd>{{ rtrim(rtrim(number_format((float) ($enquiry->requested_acreage ?? $transaction->acreage), 4, '.', ''), '0'), '.') }} acres</dd></div>
                    @if ($transaction->agreed_price !== null)
                        <div><dt class="font-medium text-lamu-900">Agreed price</dt><dd>KSh {{ number_format($transaction->agreed_price) }}</dd></div>
                    @endif
                    <div><dt class="font-medium text-lamu-900">Sales contact</dt><dd>{{ $transaction->agent?->name ?? 'Being assigned' }}</dd></div>
                </dl>
                <ol class="mt-4 space-y-2 border-t border-lamu-200 pt-4 text-sm text-stone-700">
                    @forelse ($transaction->timeline as $entry)
                        <li>{{ $entry->created_at->toDayDateTimeString() }} · {{ $entry->summary }}</li>
                    @empty
                        <li>Lamu Acres will post sales updates here as your request progresses.</li>
                    @endforelse
                </ol>
            @else
                <p class="mt-2 text-sm text-stone-700">Your request is being linked to the sales desk. Replies will appear here.</p>
            @endif
        </section>
    @endif
    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">Your enquiry</h2>
        <p class="mt-3 whitespace-pre-line text-sm text-stone-700">{{ $enquiry->message }}</p>
        <p class="mt-4 text-xs text-stone-500">Sent {{ $enquiry->created_at->toDayDateTimeString() }}</p>
    </section>

    <section class="mt-6 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Responses</h2>
        <div class="mt-4 space-y-3">
            @forelse ($enquiry->responses as $response)
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <p class="text-sm font-medium text-lamu-900">{{ $response->author->name }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-stone-700">{{ $response->body }}</p>
                    <p class="mt-3 text-xs text-stone-500">{{ $response->created_at->toDayDateTimeString() }}</p>
                </article>
            @empty
                <x-workspace.empty title="No response yet" message="Replies from Lamu Acres will appear here." />
            @endforelse
        </div>
    </section>

    <section class="mt-8 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Activity</h2>
        <x-activity-timeline
            empty="No further activity is recorded for this property yet."
            :items="collect($activity)->map(fn ($entry) => ['when' => $entry->occurred_at->toDayDateTimeString(), 'text' => $entry->summary])->all()"
        />
        <p class="mt-4 text-sm text-stone-700">When you are ready, <a href="{{ route('buyer.matches.index') }}" class="font-medium text-lamu-800 hover:underline">review your matches</a> or <a href="{{ route('land.index') }}" class="font-medium text-lamu-800 hover:underline">explore more land</a>.</p>
    </section>
</x-workspace-layout>
