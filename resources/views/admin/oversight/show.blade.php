<x-workspace-layout heading="Business Oversight">
    <p class="mt-3 max-w-3xl text-stone-700">Sales, inventory, buyers, brokers, and subscriptions counted from the records you are allowed to see.</p>

    @include('admin.partials.range', ['window' => $window])
    @include('admin.partials.filters', ['window' => $window, 'filters' => $filters])

    <section class="mt-8" aria-labelledby="oversight-section">
        <h2 id="oversight-section" class="font-semibold text-lamu-900">{{ $section['heading'] }}</h2>
        @if ($section['configured'])
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($section['metrics'] as $metric)
                    <x-kpi :label="$metric['label']" :value="$metric['value']" :href="$metric['href']" />
                @endforeach
            </div>
        @else
            <p class="mt-3 text-sm text-stone-600">Not configured</p>
        @endif
    </section>

    <x-record-tabs :tables="$tables" />
</x-workspace-layout>
