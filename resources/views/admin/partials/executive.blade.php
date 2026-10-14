@include('admin.partials.range', ['window' => $window])

<section class="mt-8" aria-labelledby="dashboard-charts">
    <h2 id="dashboard-charts" class="font-semibold text-lamu-900">Charts</h2>
    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        @foreach ($report->gallery() as $chart)
            @include('admin.partials.chart', ['chart' => $chart])
        @endforeach
    </div>
</section>

<div class="mt-8 grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($report->sections() as $section)
        <section class="rounded-2xl border border-sand-200 bg-white p-4" aria-labelledby="section-{{ \Illuminate\Support\Str::slug($section['heading']) }}">
            <h2 id="section-{{ \Illuminate\Support\Str::slug($section['heading']) }}" class="text-sm font-semibold text-lamu-900">{{ $section['heading'] }}</h2>
            @if ($section['configured'])
                <dl class="mt-2">
                    @foreach ($section['metrics'] as $metric)
                        <div class="flex items-baseline justify-between gap-4 border-t border-sand-100 py-2 first:border-t-0">
                            <dt class="text-sm text-stone-600">{{ $metric['label'] }}</dt>
                            <dd class="shrink-0 text-sm font-semibold tabular-nums text-lamu-900">
                                @if ($metric['href'])
                                    <a href="{{ $metric['href'] }}" class="hover:underline">{{ $metric['value'] }}</a>
                                @else
                                    {{ $metric['value'] }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="mt-3 text-sm text-stone-600">Not configured</p>
            @endif
        </section>
    @endforeach
</div>

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
