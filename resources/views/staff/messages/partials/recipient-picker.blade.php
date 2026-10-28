@props([
    'audience',
    'selectedIds' => [],
    'search' => '',
    'searchAction' => null,
    'channelFieldId' => null,
    'panelSuffix' => '',
])

@php
    $panelId = $channelFieldId ? 'recipient-panel'.$panelSuffix : 'recipient-panel';
    $roleLabels = ['buyer' => 'Buyer', 'seller' => 'Landowner', 'broker' => 'Broker'];
@endphp

<div id="{{ $panelId }}" @if ($channelFieldId) hidden @endif>
    @if ($searchAction)
        <form method="GET" action="{{ $searchAction }}" class="mt-4 flex flex-wrap gap-2">
            @if ($channelFieldId)
                <input type="hidden" name="channel" value="{{ request('channel') }}">
            @endif
            <label class="sr-only" for="recipient-search{{ $panelSuffix }}">Search recipients</label>
            <input id="recipient-search{{ $panelSuffix }}" name="q" value="{{ $search }}" placeholder="Search name, email, or phone" class="min-w-[12rem] flex-1 rounded-lg border border-sand-200 bg-white px-3 py-2 text-sm">
            <button type="submit" class="rounded-full border border-lamu-800 bg-white px-4 py-2 text-sm font-medium text-lamu-900">Search</button>
            @if ($search !== '')
                <a href="{{ $searchAction }}" class="rounded-full px-4 py-2 text-sm font-medium text-lamu-800 underline">Clear</a>
            @endif
        </form>
    @endif

    @if ($audience->isEmpty())
        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">No buyers, landowners, or brokers have marketing consent for this channel yet. They can opt in when registering or in profile settings under “Lamu Acres news and land opportunities”.</p>
    @else
        <div class="mb-3 mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold text-lamu-800">
            <button type="button" data-select-eligible-recipients class="rounded-full bg-lamu-900 px-3 py-1.5 text-white">Select all</button>
            <button type="button" data-select-role="buyer" class="rounded-full border border-sand-300 bg-white px-3 py-1.5">All buyers</button>
            <button type="button" data-select-role="seller" class="rounded-full border border-sand-300 bg-white px-3 py-1.5">All landowners</button>
            <button type="button" data-select-role="broker" class="rounded-full border border-sand-300 bg-white px-3 py-1.5">All brokers</button>
            <button type="button" data-clear-all-recipients class="underline">Clear</button>
            <span class="text-stone-600">{{ $audience->count() }} opted-in</span>
        </div>
        <ul class="max-h-[28rem] space-y-2 overflow-y-auto rounded-xl border border-sand-200 bg-white p-2">
            @foreach ($audience as $recipient)
                <li data-partner-roles="{{ implode(' ', $recipient['partner_roles'] ?? []) }}">
                    <label class="flex cursor-pointer gap-3 rounded-lg border border-transparent px-3 py-2 hover:bg-sand-50 has-[:checked]:border-lamu-200 has-[:checked]:bg-lamu-50/40">
                        <input
                            type="checkbox"
                            name="recipient_ids[]"
                            value="{{ $recipient['id'] }}"
                            @checked(in_array($recipient['id'], $selectedIds, true))
                            class="mt-1 size-4 rounded border-sand-300 text-lamu-900"
                        >
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-lamu-900">{{ $recipient['name'] }}</span>
                                @foreach ($recipient['partner_roles'] ?? [] as $role)
                                    <span class="rounded-full bg-sand-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-stone-700">{{ $roleLabels[$role] ?? $role }}</span>
                                @endforeach
                            </span>
                            <span class="mt-0.5 block text-xs text-stone-700">{{ $recipient['email'] ?: 'No email' }} · {{ $recipient['phone'] ?: 'No phone' }}</span>
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>
    @endif
</div>
