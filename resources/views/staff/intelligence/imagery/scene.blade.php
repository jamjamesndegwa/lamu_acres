<x-crud-page :title="$scene->exists ? 'Edit imagery scene' : 'New imagery scene'" :close="route('staff.imagery.index')" size="xl">
    <form method="POST" action="{{ $scene->exists ? route('staff.imagery.scenes.update', $scene) : route('staff.imagery.scenes.store') }}" enctype="multipart/form-data" data-turbo-frame="_top" data-confirm="Save this imagery scene?" class="mt-6 max-w-3xl space-y-4">
        @csrf
        @if ($scene->exists) @method('PUT') @endif
        <label class="block text-sm">Provider <input name="provider" value="{{ old('provider', $scene->provider) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Acquisition date <input type="date" name="acquired_on" value="{{ old('acquired_on', $scene->acquired_on?->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Resolution <input name="resolution" value="{{ old('resolution', $scene->resolution) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Coverage <input name="coverage" value="{{ old('coverage', $scene->coverage) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Cloud conditions <input name="cloud_conditions" value="{{ old('cloud_conditions', $scene->cloud_conditions) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Usage licence <input name="license_name" value="{{ old('license_name', $scene->license_name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="redistribution_allowed" value="1" @checked(old('redistribution_allowed', $scene->redistribution_allowed))> Redistribution is allowed</label>
        <label class="block text-sm">Source URL <input name="source_url" value="{{ old('source_url', $scene->source_url) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Linked project
            <select name="development_project_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="">None</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) old('development_project_id', $scene->development_project_id) === (string) $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Locality
            <select name="locality_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="">Not linked</option>
                @foreach ($localities as $locality)
                    <option value="{{ $locality->id }}" @selected((string) old('locality_id', $scene->locality_id) === (string) $locality->id)>{{ $locality->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Notes <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('notes', $scene->notes) }}</textarea></label>
        <label class="block text-sm">Image file <input type="file" name="image" accept="image/*"></label>
        @if ($errors->any()) <p class="text-sm text-red-700">{{ $errors->first() }}</p> @endif
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Save scene</button>
    </form>
    @if ($scene->exists)
        <div class="mt-4 flex gap-2">
            @if (in_array($scene->publication_status, [\App\Enums\PublicationStatus::Draft, \App\Enums\PublicationStatus::Withdrawn], true))
                <form method="POST" action="{{ route('staff.imagery.scenes.submit', $scene) }}">@csrf <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Submit</button></form>
            @endif
            @if ($scene->publication_status === \App\Enums\PublicationStatus::Review && auth()->user()->hasPermission('intelligence.publish'))
                <form method="POST" action="{{ route('staff.imagery.scenes.publish', $scene) }}">@csrf @include('admin.approvals.return-field') <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">Publish scene</button></form>
            @endif
        </div>
    @endif
</x-crud-page>
