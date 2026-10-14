<dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
    <div><dt class="text-sm text-stone-500">Reported status</dt><dd>{{ $project->project_status->label() }}</dd></div>
    <div><dt class="text-sm text-stone-500">Coverage</dt><dd>{{ $project->coverage }}</dd></div>
    <div><dt class="text-sm text-stone-500">Category</dt><dd>{{ $project->category->label() }}</dd></div>
    <div><dt class="text-sm text-stone-500">Source publication date</dt><dd>{{ $project->source_published_on?->toFormattedDateString() ?: 'Not recorded' }}</dd></div>
</dl>
<p class="mt-4 max-w-3xl text-stone-700">{{ $project->summary }}</p>
@if ($premium)
    <div class="mt-6 max-w-3xl space-y-4">
        <p><span class="font-medium text-lamu-900">Source.</span> {{ $project->source_name }} @if ($project->source_url)<a class="text-lamu-800 hover:underline" href="{{ $project->source_url }}">{{ $project->source_url }}</a>@endif</p>
        <p><span class="font-medium text-lamu-900">Verified.</span> {{ $project->verified_on?->toFormattedDateString() ?: 'Not recorded' }}</p>
        <p><span class="font-medium text-lamu-900">Research notes.</span> {{ $project->research_notes ?: 'No research notes have been recorded.' }}</p>
        @if ($project->latitude !== null && $project->longitude !== null)
            <p><span class="font-medium text-lamu-900">Coordinates.</span> {{ $project->latitude }}, {{ $project->longitude }}</p>
        @else
            <p>No coordinates have been recorded.</p>
        @endif
        @if ($project->boundary_wkt)
            <p class="text-sm text-stone-600">A boundary was supplied with the record.</p>
        @endif
        <h2 class="font-semibold text-lamu-900">Milestones</h2>
        <ul class="list-disc space-y-1 pl-5 text-sm">
            @forelse ($project->milestones as $milestone)
                <li>{{ $milestone->title }} @if ($milestone->occurred_on) · {{ $milestone->occurred_on->toFormattedDateString() }} @endif · {{ $milestone->source_name }}</li>
            @empty
                <li>No milestones have been recorded.</li>
            @endforelse
        </ul>
        <h2 class="font-semibold text-lamu-900">Evidence</h2>
        <ul class="text-sm">
            @forelse ($project->files as $file)
                <li><a class="text-lamu-800 hover:underline" href="{{ route('intelligence.files.show', $file) }}">{{ $file->original_name }}</a></li>
            @empty
                <li>No evidence file has been attached.</li>
            @endforelse
        </ul>
    </div>
@else
    <p class="mt-6 max-w-3xl text-stone-700">Sources, milestones, coordinates, research notes, and evidence are part of an active Investor Pass.</p>
@endif
