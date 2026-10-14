<x-crud-page :title="$project->exists ? 'Edit project' : 'New project'" :close="$project->exists ? route('staff.projects.show', $project) : route('staff.projects.index')" size="xl">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Leave coordinates, boundaries, and milestones empty unless a named source records them.</p>
    <form method="POST" action="{{ $project->exists ? route('staff.projects.update', $project) : route('staff.projects.store') }}" data-turbo-frame="_top" data-confirm="Save this project?" class="mt-6 max-w-3xl space-y-4">
        @csrf
        @if ($project->exists) @method('PUT') @endif
        <label class="block text-sm">Name <input name="name" value="{{ old('name', $project->name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Category
            <select name="category" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach (\App\Enums\DevelopmentCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $project->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Reported status
            <select name="project_status" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach (\App\Enums\DevelopmentProjectStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('project_status', $project->project_status?->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Geographic coverage <input name="coverage" value="{{ old('coverage', $project->coverage) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Locality
            <select name="locality_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="">Not linked</option>
                @foreach ($localities as $locality)
                    <option value="{{ $locality->id }}" @selected((string) old('locality_id', $project->locality_id) === (string) $locality->id)>{{ $locality->county->name }} — {{ $locality->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Public summary <textarea name="summary" rows="3" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('summary', $project->summary) }}</textarea></label>
        <label class="block text-sm">Research notes <textarea name="research_notes" rows="4" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('research_notes', $project->research_notes) }}</textarea></label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">Latitude <input name="latitude" value="{{ old('latitude', $project->latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
            <label class="block text-sm">Longitude <input name="longitude" value="{{ old('longitude', $project->longitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        </div>
        <label class="block text-sm">Boundary WKT <textarea name="boundary_wkt" rows="2" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('boundary_wkt', $project->boundary_wkt) }}</textarea></label>
        <label class="block text-sm">Source name <input name="source_name" value="{{ old('source_name', $project->source_name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Source URL <input name="source_url" value="{{ old('source_url', $project->source_url) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">Source publication date <input type="date" name="source_published_on" value="{{ old('source_published_on', $project->source_published_on?->toDateString()) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
            <label class="block text-sm">Verification date <input type="date" name="verified_on" value="{{ old('verified_on', $project->verified_on?->toDateString()) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        </div>
        @if ($errors->any()) <p class="text-sm text-red-700">{{ $errors->first() }}</p> @endif
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save draft</button>
    </form>
</x-crud-page>
