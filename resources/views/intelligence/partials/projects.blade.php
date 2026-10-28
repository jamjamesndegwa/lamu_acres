<x-filter-bar :action="url()->current()" class="mt-4 grid gap-3 rounded-lg border border-sand-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
    <div>
        <label for="project-status" class="block text-xs font-medium text-stone-700">Status</label>
        <select id="project-status" name="status" class="mt-1 min-h-10 w-full rounded-md border border-sand-200 bg-white px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project-type" class="block text-xs font-medium text-stone-700">Type</label>
        <select id="project-type" name="category" class="mt-1 min-h-10 w-full rounded-md border border-sand-200 bg-white px-3 py-2 text-sm">
            <option value="">All types</option>
            @foreach ($categories as $category)
                <option value="{{ $category->value }}" @selected(request('category') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project-county" class="block text-xs font-medium text-stone-700">County</label>
        <select id="project-county" name="county" class="mt-1 min-h-10 w-full rounded-md border border-sand-200 bg-white px-3 py-2 text-sm">
            <option value="">All counties</option>
            @foreach ($counties as $county)
                <option value="{{ $county->id }}" @selected((string) request('county') === (string) $county->id)>{{ $county->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project-locality" class="block text-xs font-medium text-stone-700">Location</label>
        <select id="project-locality" name="locality" class="mt-1 min-h-10 w-full rounded-md border border-sand-200 bg-white px-3 py-2 text-sm">
            <option value="">All locations</option>
            @foreach ($localities as $locality)
                <option value="{{ $locality->id }}" @selected((string) request('locality') === (string) $locality->id)>{{ $locality->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end gap-3">
        <button type="submit" class="min-h-10 rounded-md bg-lamu-900 px-4 py-2 text-xs font-semibold text-white">Apply filters</button>
        <a href="{{ url()->current() }}" class="inline-flex min-h-10 items-center text-xs font-medium text-lamu-800 underline">Reset</a>
    </div>
</x-filter-bar>

<div class="mt-5 flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="home-serif text-lg font-semibold text-lamu-900">Published developments</h2>
    <p class="text-xs text-stone-600">{{ $projects->total() }} {{ $projects->total() === 1 ? 'record' : 'records' }} matching your selection</p>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($projects as $project)
        <article class="flex flex-col rounded-lg border border-sand-200 bg-white p-4">
            <p class="text-xs font-medium text-gold-500">{{ $project->category->label() }} · {{ $project->project_status->label() }}</p>
            <h3 class="home-serif mt-2 text-base font-semibold leading-snug text-lamu-900">
                <a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.projects.show' : 'intelligence.projects.show', $project) }}">{{ $project->name }}</a>
            </h3>
            <p class="mt-2 text-xs leading-relaxed text-stone-600">{{ $project->coverage }}</p>
            <p class="mt-2 text-xs leading-relaxed text-stone-700">{{ $project->project_status->statement() }}</p>
            <p class="mt-2 text-xs text-stone-500">{{ $project->locality?->name }}{{ $project->county ? ', '.$project->county->name : '' }}</p>
            <p class="mt-1 text-xs text-stone-500">Source {{ $project->source_name }} · published {{ $project->source_published_on?->toFormattedDateString() ?: 'not recorded' }} · verified {{ $project->verified_on?->toFormattedDateString() ?: 'not recorded' }}</p>
            <a href="{{ route($frame === 'workspace' ? 'investor.projects.show' : 'intelligence.projects.show', $project) }}" class="mt-3 inline-flex min-h-9 items-center self-start text-xs font-semibold text-lamu-800 hover:underline">View development →</a>
        </article>
    @empty
        <div class="rounded-lg border border-sand-200 bg-white p-5 sm:col-span-2 lg:col-span-3">
            <h3 class="home-serif text-base font-semibold text-lamu-900">No published developments match this selection</h3>
            <p class="mt-2 text-sm text-stone-600">Try another location or project status, or reset the filters to browse all published records.</p>
            <p class="mt-2 text-xs text-stone-500">Only published records appear here. Proposed projects are not presented as completed infrastructure.</p>
            <a href="{{ url()->current() }}" class="mt-3 inline-block text-xs font-semibold text-lamu-800 underline">Clear filters</a>
        </div>
    @endforelse
</div>
<div class="mt-4">{{ $projects->links() }}</div>
