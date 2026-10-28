<div class="mt-5 flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="home-serif text-lg font-semibold text-lamu-900">Published research reports</h2>
    <p class="text-xs text-stone-600">{{ $reports->total() }} reports matching your selection</p>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($reports as $report)
        <article class="flex flex-col rounded-lg border border-sand-200 bg-white p-4">
            <h3 class="home-serif text-base font-semibold leading-snug text-lamu-900"><a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.reports.show' : 'intelligence.reports.show', $report) }}">{{ $report->title }}</a></h3>
            <p class="mt-2 text-xs leading-relaxed text-stone-600">{{ $report->summary }}</p>
            <p class="mt-2 text-xs text-stone-500">{{ $report->report_date?->toFormattedDateString() ?: $report->published_at?->toFormattedDateString() ?: 'Date not recorded' }}@if ($report->localities->isNotEmpty()) · {{ $report->localities->pluck('name')->join(', ') }}@endif</p>
            @include('intelligence.partials.report-facts', ['report' => $report, 'premium' => $premium])
            <a href="{{ route($frame === 'workspace' ? 'investor.reports.show' : 'intelligence.reports.show', $report) }}" class="mt-3 inline-flex min-h-9 items-center self-start text-xs font-semibold text-lamu-800 hover:underline">Read report →</a>
        </article>
    @empty
        <p class="rounded-lg border border-sand-200 bg-white p-5 text-sm text-stone-600 sm:col-span-2 lg:col-span-3">No research report has been published.</p>
    @endforelse
</div>
<div class="mt-4">{{ $reports->links() }}</div>
