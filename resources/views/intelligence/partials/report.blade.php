<p class="mt-4 max-w-3xl text-stone-700">{{ $report->summary }}</p>
<p class="mt-2 text-sm text-stone-500">Publication date: {{ $report->published_at?->toFormattedDateString() ?: 'Not published' }} @if ($report->report_date) · report date {{ $report->report_date->toFormattedDateString() }} @endif</p>
@if ($premium)
    <div class="mt-6 max-w-3xl space-y-4">
        <div class="whitespace-pre-wrap">{{ $report->body }}</div>
        <h2 class="font-semibold text-lamu-900">Sources</h2>
        <ul class="list-disc pl-5 text-sm">
            @foreach ($report->citations as $citation)
                <li>{{ $citation->label }} @if ($citation->url) · {{ $citation->url }} @endif</li>
            @endforeach
        </ul>
        @if ($report->projects->isNotEmpty())
            <p>Projects: {{ $report->projects->pluck('name')->join(', ') }}</p>
        @endif
        <h2 class="font-semibold text-lamu-900">Revisions</h2>
        <ul class="text-sm text-stone-600">
            @foreach ($report->audits->whereIn('action', ['published', 'revised']) as $audit)
                <li>{{ $audit->created_at?->toDayDateTimeString() }} · {{ $audit->action }}</li>
            @endforeach
        </ul>
    </div>
@else
    <p class="mt-6 text-stone-700">The report, its citations, and its revision history are part of an active Investor Pass.</p>
@endif
