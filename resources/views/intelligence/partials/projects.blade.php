<x-filter-bar :action="url()->current()" class="mt-6 flex flex-wrap items-end gap-3">
    <div>
        <label for="project-status" class="block text-sm font-medium">Status</label>
        <select id="project-status" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project-type" class="block text-sm font-medium">Type</label>
        <select id="project-type" name="category" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All types</option>
            @foreach ($categories as $category)
                <option value="{{ $category->value }}" @selected(request('category') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project-county" class="block text-sm font-medium">County</label>
        <select id="project-county" name="county" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All counties</option>
            @foreach ($counties as $county)
                <option value="{{ $county->id }}" @selected((string) request('county') === (string) $county->id)>{{ $county->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project-locality" class="block text-sm font-medium">Location</label>
        <select id="project-locality" name="locality" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All locations</option>
            @foreach ($localities as $locality)
                <option value="{{ $locality->id }}" @selected((string) request('locality') === (string) $locality->id)>{{ $locality->name }}</option>
            @endforeach
        </select>
    </div>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Filter</button>
</x-filter-bar>

<div class="mt-6 grid gap-4">
    @forelse ($projects as $project)
        <article class="rounded-2xl border border-sand-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gold-500">{{ $project->category->label() }} · {{ $project->project_status->label() }}</p>
            <h2 class="mt-1 text-lg font-semibold text-lamu-900">
                <a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.projects.show' : 'intelligence.projects.show', $project) }}">{{ $project->name }}</a>
            </h2>
            <p class="mt-2 text-sm text-stone-600">{{ $project->coverage }}</p>
            <p class="mt-2 text-sm text-stone-700">{{ $project->project_status->statement() }}</p>
            <p class="mt-1 text-sm text-stone-500">{{ $project->county?->name }} {{ $project->locality?->name }}</p>
        </article>
    @empty
        <p class="text-stone-600">No development has been published. This page does not estimate projects, milestones, or coordinates.</p>
    @endforelse
</div>
<div class="mt-4">{{ $projects->links() }}</div>
