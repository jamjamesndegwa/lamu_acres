<dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
    <div>
        <dt class="font-medium text-lamu-900">Research date</dt>
        <dd class="text-stone-700">{{ $report->report_date?->toFormattedDateString() ?: 'Not recorded' }}</dd>
    </div>
    <div>
        <dt class="font-medium text-lamu-900">Last review</dt>
        <dd class="text-stone-700">{{ $report->published_at?->toFormattedDateString() ?: 'Not recorded' }}</dd>
    </div>
    <div class="sm:col-span-2">
        <dt class="font-medium text-lamu-900">Source</dt>
        <dd class="text-stone-700">
            @if ($premium)
                @forelse ($report->citations as $citation)
                    <span class="block">{{ $citation->label }}@if ($citation->url) · {{ $citation->url }}@endif</span>
                @empty
                    No source has been recorded.
                @endforelse
            @elseif ($report->citations->isNotEmpty())
                A source is on file. The citation text is part of an active Investor Pass.
            @else
                No source has been recorded.
            @endif
        </dd>
    </div>
    <div class="sm:col-span-2">
        <dt class="font-medium text-lamu-900">Project status</dt>
        <dd class="text-stone-700">
            @forelse ($report->projects->filter(fn ($project) => $project->isReleased()) as $project)
                <span class="block">{{ $project->name }} · {{ $project->project_status->label() }}. {{ $project->project_status->statement() }}</span>
            @empty
                No linked project has been published.
            @endforelse
        </dd>
    </div>
    <div class="sm:col-span-2">
        <dt class="font-medium text-lamu-900">Uncertainty</dt>
        <dd class="text-stone-700">This page repeats the published status only. It does not add an operating claim.</dd>
    </div>
</dl>
