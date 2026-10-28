@php
    $countyContext ??= false;
@endphp

<section class="rounded-2xl border border-sand-200 bg-white p-5 sm:p-6">
    <h2 class="home-serif text-lg font-semibold text-lamu-900">{{ $heading }}</h2>
    <p class="mt-1 text-sm text-stone-600">{{ $intro }}</p>
    @if ($countyContext)
        <p class="mt-2 text-xs font-medium uppercase tracking-wide text-stone-500">County context</p>
    @endif
    @forelse ($projects as $project)
        <article class="mt-4 border-t border-sand-100 pt-4 first:mt-3 first:border-0 first:pt-0">
            <h3 class="font-medium text-lamu-900"><a href="{{ route('investor.projects.show', $project) }}" class="hover:underline">{{ $project->name }}</a></h3>
            <p class="text-xs text-stone-500">{{ $project->project_status->label() }} · Published {{ $project->published_at?->toFormattedDateString() }}</p>
            <p class="mt-1 text-sm text-stone-600">{{ $project->summary }}</p>
            <p class="mt-1 text-xs text-stone-500">Source: {{ $project->source_name }}@if ($project->verified_on) · Verified {{ $project->verified_on->toFormattedDateString() }}@endif</p>
        </article>
    @empty
        <p class="mt-3 text-sm text-stone-600">No released projects in this group.</p>
    @endforelse
</section>
