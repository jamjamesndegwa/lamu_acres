<x-workspace-layout heading="External communications">
    @if ($stalledQueueWarning)
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $stalledQueueWarning }}
        </div>
    @endif

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">Generated</p>
            <p class="mt-1 text-2xl font-semibold text-lamu-900">{{ number_format($counts['total']) }}</p>
        </article>
        <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">Queued</p>
            <p class="mt-1 text-2xl font-semibold text-lamu-900">{{ number_format($counts['queued']) }}</p>
        </article>
        <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">Sent</p>
            <p class="mt-1 text-2xl font-semibold text-lamu-900">{{ number_format($counts['submitted']) }}</p>
        </article>
        <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">Failed / uncertain / skipped</p>
            <p class="mt-1 text-stone-700">
                {{ number_format($counts['failed']) }} failed ·
                {{ number_format($counts['uncertain']) }} uncertain ·
                {{ number_format($counts['skipped']) }} skipped
            </p>
        </article>
    </div>

    <form method="GET" action="{{ route($indexRoute) }}" class="mt-6 grid gap-3 rounded-2xl border border-sand-200 bg-white p-4 md:grid-cols-4">
        <label class="text-sm">
            <span class="font-medium text-lamu-900">Search</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2" placeholder="Recipient, subject, email">
        </label>
        <label class="text-sm">
            <span class="font-medium text-lamu-900">Channel</span>
            <select name="channel" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
                <option value="">All permitted</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel->value }}" @selected(($filters['channel'] ?? '') === $channel->value)>{{ $channel->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="font-medium text-lamu-900">Status</span>
            <select name="status" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
                <option value="">Any</option>
                @foreach (['queued', 'processing', 'sent', 'failed', 'uncertain', 'skipped', 'not_sent'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="font-medium text-lamu-900">Kind</span>
            <select name="kind" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
                <option value="">Any</option>
                <option value="transactional" @selected(($filters['kind'] ?? '') === 'transactional')>Transactional</option>
                <option value="campaign" @selected(($filters['kind'] ?? '') === 'campaign')>Campaign</option>
                <option value="security" @selected(($filters['kind'] ?? '') === 'security')>Security</option>
            </select>
        </label>
        <label class="text-sm">
            <span class="font-medium text-lamu-900">From</span>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
        </label>
        <label class="text-sm">
            <span class="font-medium text-lamu-900">To</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
        </label>
        <label class="text-sm md:col-span-2">
            <span class="font-medium text-lamu-900">Event</span>
            <select name="event" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
                <option value="">Any</option>
                @foreach ($events as $event)
                    <option value="{{ $event->value }}" @selected(($filters['event'] ?? '') === $event->value)>{{ $event->label() }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2 md:col-span-4">
            <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Apply filters</button>
            <a href="{{ route($indexRoute) }}" class="rounded-full border border-sand-200 px-4 py-2 text-sm font-medium text-lamu-900">Reset</a>
        </div>
    </form>

    <div class="mt-6 overflow-x-auto rounded-2xl border border-sand-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-sand-200 bg-sand-50 text-xs uppercase tracking-wide text-stone-600">
                <tr>
                    <th class="px-4 py-3">Generated</th>
                    <th class="px-4 py-3">Channel</th>
                    <th class="px-4 py-3">Recipient</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Preview</th>
                    <th class="px-4 py-3">Event</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Attempts</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deliveries as $delivery)
                    <tr class="border-b border-sand-100 align-top">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $delivery->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ $delivery->channel->label() }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-lamu-900">{{ $delivery->recipient?->name ?? '—' }}</p>
                            <p class="text-xs text-stone-500">{{ $delivery->destination }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $delivery->subject }}</td>
                        <td class="px-4 py-3 max-w-xs text-stone-700">{{ $register->previewBody($delivery) }}</td>
                        <td class="px-4 py-3">{{ $delivery->event->label() }}</td>
                        <td class="px-4 py-3">
                            @if ($delivery->status === 'uncertain')
                                <span class="text-amber-800">Uncertain</span>
                            @else
                                {{ ucfirst(str_replace('_', ' ', $delivery->status)) }}
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $delivery->attempts }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route($showRoute, $delivery) }}" class="font-medium text-lamu-900 underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-stone-600">No external messages match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $deliveries->links() }}</div>
</x-workspace-layout>
