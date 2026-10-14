@php
    $before = $comparison->beforeScene;
    $after = $comparison->afterScene;
    $showBefore = $premium && $before?->canRedistribute() && $before->files->isNotEmpty();
    $showAfter = $premium && $after?->canRedistribute() && $after->files->isNotEmpty();
@endphp
<div class="mt-6 grid gap-4 md:grid-cols-2">
    <figure class="rounded-2xl border border-sand-200 bg-white p-4">
        <figcaption class="text-sm font-medium text-lamu-900">Before · {{ $before?->acquired_on?->toFormattedDateString() }} · {{ $before?->provider }}</figcaption>
        <p class="mt-1 text-xs text-stone-500">{{ $before?->resolution }} @if ($before?->cloud_conditions) · {{ $before->cloud_conditions }} @endif · {{ $before?->license_name }}</p>
        @if ($showBefore)
            <img src="{{ route('intelligence.files.show', $before->files->first()) }}" alt="Earlier authorised image of {{ $comparison->area_label }}" class="mt-3 w-full rounded-xl">
        @else
            <p class="mt-3 text-sm text-stone-600">This earlier image is not available for redistribution.</p>
        @endif
    </figure>
    <figure class="rounded-2xl border border-sand-200 bg-white p-4">
        <figcaption class="text-sm font-medium text-lamu-900">After · {{ $after?->acquired_on?->toFormattedDateString() }} · {{ $after?->provider }}</figcaption>
        <p class="mt-1 text-xs text-stone-500">{{ $after?->resolution }} @if ($after?->cloud_conditions) · {{ $after->cloud_conditions }} @endif · {{ $after?->license_name }}</p>
        @if ($showAfter)
            <img src="{{ route('intelligence.files.show', $after->files->first()) }}" alt="Later authorised image of {{ $comparison->area_label }}" class="mt-3 w-full rounded-xl">
        @else
            <p class="mt-3 text-sm text-stone-600">This later image is not available for redistribution.</p>
        @endif
    </figure>
</div>
@if ($premium)
    <div class="mt-6 max-w-3xl space-y-3">
        <p><span class="font-medium text-lamu-900">Observed change.</span> {{ $comparison->observed_change }}</p>
        @if ($comparison->annotation)
            <p><span class="font-medium text-lamu-900">Researcher annotation.</span> {{ $comparison->annotation }}</p>
        @endif
        @if ($comparison->project)
            <p><span class="font-medium text-lamu-900">Linked project.</span> {{ $comparison->project->name }}</p>
        @endif
    </div>
@else
    <p class="mt-6 max-w-3xl text-stone-700">The observed change and any authorised images are part of an active Investor Pass.</p>
@endif
