<x-crud-page :title="$report->exists ? 'Edit report' : 'New report'" :close="route('staff.reports.index')" size="xl">
    <form method="POST" action="{{ $report->exists ? route('staff.reports.update', $report) : route('staff.reports.store') }}" data-turbo-frame="_top" data-confirm="Save this report?" class="mt-6 max-w-3xl space-y-4">
        @csrf
        @if ($report->exists) @method('PUT') @endif
        <label class="block text-sm">Title <input name="title" value="{{ old('title', $report->title) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Public summary <textarea name="summary" rows="3" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('summary', $report->summary) }}</textarea></label>
        <label class="block text-sm">Premium report <textarea name="body" rows="8" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('body', $report->body) }}</textarea></label>
        <label class="block text-sm">Report date <input type="date" name="report_date" value="{{ old('report_date', $report->report_date?->toDateString()) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Citation <input name="citation_label" value="{{ old('citation_label', optional($report->citations->first())->label) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Citation URL <input name="citation_url" value="{{ old('citation_url', optional($report->citations->first())->url) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <fieldset>
            <legend class="text-sm font-medium">Projects</legend>
            @foreach ($projects as $project)
                <label class="mt-1 flex gap-2 text-sm"><input type="checkbox" name="project_ids[]" value="{{ $project->id }}" @checked($report->exists && $report->projects->contains($project))> {{ $project->name }}</label>
            @endforeach
        </fieldset>
        <fieldset>
            <legend class="text-sm font-medium">Localities</legend>
            @foreach ($localities as $locality)
                <label class="mt-1 flex gap-2 text-sm"><input type="checkbox" name="locality_ids[]" value="{{ $locality->id }}" @checked($report->exists && $report->localities->contains($locality))> {{ $locality->name }}</label>
            @endforeach
        </fieldset>
        @if ($errors->any()) <p class="text-sm text-red-700">{{ $errors->first() }}</p> @endif
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Save</button>
    </form>
</x-crud-page>
