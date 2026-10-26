<x-workspace-layout :heading="$section['heading']">
    <p class="mt-3 max-w-3xl text-stone-700">{{ $intro }}</p>

    @include('admin.partials.range', ['window' => $window])
    @include('admin.partials.filters', ['window' => $window, 'filters' => $filters])

    @if ($requestUser = auth()->user())
        @if ($requestUser->hasPermission('admin.reports'))
            <p class="mt-4 text-sm text-stone-600">
                <a href="{{ route('admin.reports.export', ['dataset' => $tab === 'financial' ? 'agency-earnings' : ($tab === 'commissions' ? 'broker-commissions' : 'completed-sales'), ...array_merge($window->query(), $filters->query())]) }}" class="font-medium text-lamu-800 hover:underline">Download CSV</a>
                for the current period and filters.
            </p>
        @endif
    @endif

    <section class="mt-8" aria-labelledby="report-section">
        <h2 id="report-section" class="font-semibold text-lamu-900">{{ $section['heading'] }}</h2>
        @if ($section['configured'])
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($section['metrics'] as $metric)
                    <x-kpi :label="$metric['label']" :value="$metric['value']" :href="$metric['href']" />
                @endforeach
            </div>
        @else
            <x-state class="mt-3" message="The records for this report are not available." />
        @endif
    </section>

    <x-record-tabs :tables="$tables" empty="No records in this period." />
</x-workspace-layout>
