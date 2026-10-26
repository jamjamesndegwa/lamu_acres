<section aria-labelledby="dashboard-charts">
    <h2 id="dashboard-charts" class="font-semibold text-lamu-900">Overview charts</h2>
    <p class="mt-2 max-w-3xl text-sm text-stone-600">All-time figures from listings, sales, demand, brokers, finance, subscriptions, and verification. No date filter is applied here.</p>
    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        @foreach ($report->gallery() as $chart)
            @include('admin.partials.chart', ['chart' => $chart])
        @endforeach
    </div>
</section>

<section class="mt-8" aria-labelledby="recent-activity">
    <h2 id="recent-activity" class="font-semibold text-lamu-900">Recent activity</h2>
    <x-workspace.table class="mt-4" labelled-by="recent-activity" :columns="['Event', 'Date']">
        @forelse ($activity as $event)
            <tr class="border-b border-sand-200 last:border-b-0">
                <td class="px-4 py-4 text-sm text-lamu-900">
                    @if ($event['href'])
                        <a href="{{ $event['href'] }}" class="font-medium hover:underline">{{ $event['description'] }}</a>
                    @else
                        {{ $event['description'] }}
                    @endif
                </td>
                <td class="px-4 py-4 text-sm text-stone-600">{{ $event['at']->toDayDateTimeString() }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="px-4 py-8">
                    <x-workspace.empty message="No activity yet" />
                </td>
            </tr>
        @endforelse
    </x-workspace.table>
</section>
