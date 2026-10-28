<p class="mt-4 max-w-3xl text-stone-700">{{ $report->summary }}</p>
<div class="mt-4 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
    <p class="text-sm text-stone-500">Publication status: {{ $report->publication_status->label() }}</p>
    @include('intelligence.partials.report-facts', ['report' => $report, 'premium' => $premium])
</div>
@if ($premium)
    <div class="mt-6 max-w-3xl rounded-2xl border border-lamu-200 bg-lamu-50 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-lamu-800">Subscriber analysis</p>
    <div class="mt-4 space-y-4">
        <div class="whitespace-pre-wrap">{{ $report->body }}</div>
        <h2 class="font-semibold text-lamu-900">Sources</h2>
        <ul class="list-disc pl-5 text-sm">
            @foreach ($report->citations as $citation)
                <li>{{ $citation->label }} @if ($citation->url) · {{ $citation->url }} @endif</li>
            @endforeach
        </ul>
        @if ($report->localities->isNotEmpty())
            <p>Locations: {{ $report->localities->pluck('name')->join(', ') }}</p>
        @endif
        @if ($report->projects->isNotEmpty())
            <p>Projects: {{ $report->projects->pluck('name')->join(', ') }}</p>
        @endif
        <h2 class="font-semibold text-lamu-900">Licensed files</h2>
        <ul class="text-sm">
            @forelse ($report->files as $file)
                <li><a class="text-lamu-800 hover:underline" data-turbo="false" href="{{ route('intelligence.files.show', $file) }}">{{ $file->original_name }}</a></li>
            @empty
                <li>No downloadable file has been licensed for this report.</li>
            @endforelse
        </ul>
        <h2 class="font-semibold text-lamu-900">Revisions</h2>
        <ul class="text-sm text-stone-600">
            @foreach ($report->audits->whereIn('action', ['published', 'revised']) as $audit)
                <li>{{ $audit->created_at?->toDayDateTimeString() }} · {{ $audit->action }}</li>
            @endforeach
        </ul>
    </div>
    </div>
@else
    <p class="mt-6 text-stone-700">The report, its citations, and its revision history are part of an active Investor Pass.</p>
@endif
