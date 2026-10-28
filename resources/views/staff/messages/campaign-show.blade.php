@php
    $isDraft = $campaign->status === \App\Enums\MessageCampaignStatus::Draft;
    $selectedIds = $campaign->recipients->pluck('id')->all();
    $destinationLabel = $campaign->channel === \App\Enums\MessageChannel::Email ? 'Email' : 'Phone';
@endphp

<x-workspace-layout :heading="$campaign->subject">
    @if (session('status'))
        <p class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <section class="rounded-2xl border border-sand-200 bg-white p-5 lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Campaign</p>
            <p class="mt-1 font-medium text-lamu-900">{{ $campaign->channel->label() }} · {{ $campaign->status->label() }}</p>
            <h2 class="mt-3 home-serif text-lg font-semibold text-lamu-900">{{ $campaign->subject }}</h2>
            <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-stone-800">{{ $campaign->body }}</p>
            <dl class="mt-4 grid gap-2 text-sm text-stone-700">
                <div class="flex justify-between gap-4 border-t border-sand-100 pt-2"><dt>Selected recipients</dt><dd class="font-semibold text-lamu-900">{{ $campaign->recipients->count() }}</dd></div>
                @unless ($isDraft)
                    <div class="flex justify-between gap-4"><dt>Queued total</dt><dd>{{ $campaign->recipient_count }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Sent</dt><dd>{{ $campaign->sent_count }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Failed</dt><dd>{{ $campaign->failed_count }}</dd></div>
                @endunless
            </dl>
            @if ($isDraft)
                <form method="POST" action="{{ route('staff.messages.campaigns.queue', $campaign) }}" data-turbo-frame="_top" data-confirm="Queue this campaign for delivery to {{ $campaign->recipients->count() }} recipient(s)?" class="mt-5">
                    @csrf
                    <button type="submit" @disabled($campaign->recipients->isEmpty()) class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-stone-400">
                        Queue delivery
                    </button>
                </form>
                @if ($campaign->recipients->isEmpty())
                    <p class="mt-2 text-xs text-amber-900">Select at least one eligible recipient, then save recipients below.</p>
                @endif
            @endif
        </section>

        @if ($isDraft)
            <section class="rounded-2xl border border-lamu-200 bg-lamu-50/30 p-5 lg:col-span-3" aria-labelledby="campaign-recipients-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="campaign-recipients-heading" class="home-serif text-lg font-semibold text-lamu-900">Select recipients</h2>
                        <p class="mt-1 text-sm text-stone-800">Only opted-in buyers, landowners, and brokers are listed. Use Select all, All buyers, All landowners, or All brokers, then save.</p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-lamu-900 ring-1 ring-sand-200">{{ count($selectedIds) }} saved</span>
                </div>

                <form method="GET" action="{{ route('staff.messages.campaigns.show', $campaign) }}" class="mt-4 flex flex-wrap gap-2">
                    <label class="sr-only" for="recipient-search">Search recipients</label>
                    <input id="recipient-search" name="q" value="{{ $recipientSearch }}" placeholder="Search name, email, or phone" class="min-w-[12rem] flex-1 rounded-lg border border-sand-200 bg-white px-3 py-2 text-sm">
                    <button type="submit" class="rounded-full border border-lamu-800 bg-white px-4 py-2 text-sm font-medium text-lamu-900">Search</button>
                    @if ($recipientSearch !== '')
                        <a href="{{ route('staff.messages.campaigns.show', $campaign) }}" class="rounded-full px-4 py-2 text-sm font-medium text-lamu-800 underline">Clear</a>
                    @endif
                </form>

                <form method="POST" action="{{ route('staff.messages.campaigns.recipients.update', $campaign) }}" data-turbo-frame="_top" data-campaign-recipients-form class="mt-4">
                    @csrf
                    @method('PUT')

                    @include('staff.messages.partials.recipient-picker', [
                        'audience' => $audience,
                        'selectedIds' => $selectedIds,
                        'search' => $recipientSearch,
                    ])

                    <button type="submit" class="mt-4 rounded-full bg-lamu-900 px-5 py-2.5 text-sm font-semibold text-white">Save recipients</button>
                </form>
            </section>
        @else
            <section class="rounded-2xl border border-sand-200 bg-white p-5 lg:col-span-3">
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Recipients</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($campaign->recipients as $recipient)
                        <li class="rounded-lg bg-sand-50 px-3 py-2 text-stone-800">{{ $recipient->name }} · {{ $campaign->channel === \App\Enums\MessageChannel::Email ? $recipient->email : $recipient->phone }}</li>
                    @empty
                        <li class="text-stone-600">No recipients were stored for this campaign.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>

    <h2 class="mt-10 home-serif text-lg font-semibold text-lamu-900">Recent deliveries</h2>
    <div class="mt-4 space-y-2">
        @forelse ($campaign->deliveries as $delivery)
            <article class="rounded-xl border border-sand-200 bg-white p-3 text-sm text-stone-800">
                <p>{{ $delivery->recipient?->name }} · {{ $delivery->status }}@if ($delivery->provider_reference) · ref {{ $delivery->provider_reference }}@endif</p>
            </article>
        @empty
            <p class="text-sm text-stone-700">Deliveries appear after the campaign is queued.</p>
        @endforelse
    </div>

    @if ($isDraft)
        @include('staff.messages.partials.recipient-picker-scripts')
    @endif
</x-workspace-layout>
