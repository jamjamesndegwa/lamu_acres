@if (is_array($chart) && (($chart['rows'] ?? []) !== [] || ($chart['series'] ?? []) !== []))
    <div class="rounded-2xl border border-sand-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-lamu-900">{{ $chart['heading'] }}</h3>
        @if (($chart['type'] ?? 'bar') === 'polygon')
            @include('admin.partials.charts.polygon', ['chart' => $chart])
        @elseif (($chart['type'] ?? '') === 'line')
            @include('admin.partials.charts.line', ['chart' => $chart])
        @else
            @include('admin.partials.charts.bar', ['chart' => $chart])
        @endif
    </div>
@endif
