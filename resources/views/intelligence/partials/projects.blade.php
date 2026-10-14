<div class="mt-6 grid gap-4">
    @forelse ($projects as $project)
        <article class="rounded-2xl border border-sand-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gold-500">{{ $project->category->label() }} · {{ $project->project_status->label() }}</p>
            <h2 class="mt-1 text-lg font-semibold text-lamu-900">
                <a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.projects.show' : 'intelligence.projects.show', $project) }}">{{ $project->name }}</a>
            </h2>
            <p class="mt-2 text-sm text-stone-600">{{ $project->coverage }}</p>
        </article>
    @empty
        <p class="text-stone-600">No development has been published. This page does not estimate projects, milestones, or coordinates.</p>
    @endforelse
</div>
<div class="mt-4">{{ $projects->links() }}</div>
