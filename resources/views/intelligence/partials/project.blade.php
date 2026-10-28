<dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
    <div><dt class="text-sm text-stone-500">Reported status</dt><dd>{{ $project->project_status->label() }}</dd></div>
    <div><dt class="text-sm text-stone-500">Coverage</dt><dd>{{ $project->coverage }}</dd></div>
    <div><dt class="text-sm text-stone-500">Category</dt><dd>{{ $project->category->label() }}</dd></div>
    <div><dt class="text-sm text-stone-500">Source publication date</dt><dd>{{ $project->source_published_on?->toFormattedDateString() ?: 'Not recorded' }}</dd></div>
    <div><dt class="text-sm text-stone-500">Published</dt><dd>{{ $project->published_at?->toFormattedDateString() ?: 'Not recorded' }}</dd></div>
    <div><dt class="text-sm text-stone-500">Last review</dt><dd>{{ $project->verified_on?->toFormattedDateString() ?: ($project->published_at?->toFormattedDateString() ?: 'Not recorded') }}</dd></div>
</dl>
<p class="mt-4 max-w-3xl text-sm text-stone-700">{{ $project->project_status->statement() }}</p>
<p class="mt-4 max-w-3xl text-stone-700">{{ $project->summary }}</p>
@if ($frame === 'workspace' && auth()->user()?->hasActiveInvestorPass())
    <div class="mt-4 max-w-3xl">
        <x-investor-watch-control
            :watch="$projectWatch ?? null"
            :project-id="$project->id"
            label="Watch this project"
            watching-label="Watching project"
        />
        <p class="mt-2 text-xs text-stone-500">Alerts are sent after verified research is published. Draft material is never listed.</p>
    </div>
@endif
@if ($premium)
    <div class="mt-6 max-w-3xl space-y-4">
        <p><span class="font-medium text-lamu-900">Source.</span> {{ $project->source_name }} @if ($project->source_url)<a class="text-lamu-800 hover:underline" href="{{ $project->source_url }}">{{ $project->source_url }}</a>@endif</p>
        <p><span class="font-medium text-lamu-900">Verified.</span> {{ $project->verified_on?->toFormattedDateString() ?: 'Not recorded' }}</p>
        <p><span class="font-medium text-lamu-900">Research notes.</span> {{ $project->research_notes ?: 'No research notes have been recorded.' }}</p>
        <p><span class="font-medium text-lamu-900">Uncertainty.</span> {{ $project->research_notes ? 'The research note records what is still unconfirmed.' : 'No separate uncertainty note has been recorded beyond the published status.' }}</p>
        @if ($project->latitude !== null && $project->longitude !== null)
            <p><span class="font-medium text-lamu-900">Coordinates.</span> {{ $project->latitude }}, {{ $project->longitude }}</p>
            <x-map-viewer :latitude="$project->latitude" :longitude="$project->longitude" />
            <p class="text-sm text-stone-600">The marker is the published research point. It is not a parcel boundary and it is not a direction to a private holding.</p>
        @else
            <p>No coordinates have been recorded.</p>
        @endif
        @if ($project->boundary_wkt)
            <p><span class="font-medium text-lamu-900">Verified boundary.</span></p>
            <pre class="overflow-x-auto rounded-lg bg-sand-50 p-3 text-xs text-stone-700">{{ $project->boundary_wkt }}</pre>
        @else
            <p>No verified boundary has been recorded.</p>
        @endif
        <h2 class="font-semibold text-lamu-900">Milestones</h2>
        <ul class="list-disc space-y-1 pl-5 text-sm">
            @forelse ($project->milestones as $milestone)
                <li>{{ $milestone->title }} @if ($milestone->occurred_on) · {{ $milestone->occurred_on->toFormattedDateString() }} @endif · {{ $milestone->source_name }}</li>
            @empty
                <li>No milestones have been recorded.</li>
            @endforelse
        </ul>
        <h2 class="font-semibold text-lamu-900">Change history</h2>
        <x-activity-timeline
            empty="No changes have been recorded."
            :items="$project->audits->map(fn ($audit) => [
                'when' => $audit->created_at?->toDayDateTimeString(),
                'text' => trim($audit->action.($audit->actor ? ' · '.$audit->actor->name : '')),
            ])->all()"
        />
        <h2 class="font-semibold text-lamu-900">Related research</h2>
        <ul class="list-disc pl-5 text-sm">
            @forelse ($relatedReports ?? [] as $report)
                <li><a class="text-lamu-800 hover:underline" href="{{ route($frame === 'workspace' ? 'investor.reports.show' : 'intelligence.reports.show', $report) }}">{{ $report->title }}</a></li>
            @empty
                <li>No linked report has been published.</li>
            @endforelse
        </ul>
        <h2 class="font-semibold text-lamu-900">Related imagery</h2>
        <ul class="list-disc pl-5 text-sm">
            @forelse ($relatedComparisons ?? [] as $comparison)
                <li><a class="text-lamu-800 hover:underline" href="{{ route($frame === 'workspace' ? 'investor.imagery.show' : 'intelligence.imagery.show', $comparison) }}">{{ $comparison->area_label }}</a></li>
            @empty
                <li>No imagery comparison has been linked.</li>
            @endforelse
        </ul>
        <h2 class="font-semibold text-lamu-900">Evidence</h2>
        <ul class="text-sm">
            @forelse ($project->files as $file)
                <li><a class="text-lamu-800 hover:underline" data-turbo="false" href="{{ route('intelligence.files.show', $file) }}">{{ $file->original_name }}</a></li>
            @empty
                <li>No evidence file has been attached.</li>
            @endforelse
        </ul>
    </div>
@else
    <p class="mt-6 max-w-3xl text-stone-700">Sources, milestones, coordinates, research notes, and evidence are part of an active Investor Pass.</p>
@endif
