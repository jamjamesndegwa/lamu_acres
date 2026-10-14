<x-crud-page title="New comparison" :close="route('staff.imagery.index')" size="xl">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Describe only the change a researcher has observed. This form does not detect construction.</p>
    <form method="POST" action="{{ route('staff.imagery.comparisons.store') }}" data-turbo-frame="_top" data-confirm="Save this comparison?" class="mt-6 max-w-3xl space-y-4">
        @csrf
        <label class="block text-sm">Area <input name="area_label" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Before
            <select name="before_scene_id" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach ($scenes as $scene)
                    <option value="{{ $scene->id }}">{{ $scene->reference }} · {{ $scene->acquired_on->toFormattedDateString() }} · {{ $scene->coverage }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">After
            <select name="after_scene_id" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach ($scenes as $scene)
                    <option value="{{ $scene->id }}">{{ $scene->reference }} · {{ $scene->acquired_on->toFormattedDateString() }} · {{ $scene->coverage }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Linked project
            <select name="development_project_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="">None</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Observed change <textarea name="observed_change" rows="4" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea></label>
        <label class="block text-sm">Annotation <textarea name="annotation" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea></label>
        @if ($errors->any()) <p class="text-sm text-red-700">{{ $errors->first() }}</p> @endif
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Save comparison</button>
    </form>
</x-crud-page>
