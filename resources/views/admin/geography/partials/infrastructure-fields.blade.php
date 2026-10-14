<form method="POST" action="{{ $route }}" data-turbo-frame="_top" data-confirm="Save this infrastructure reference?" class="space-y-4">
    @csrf
    @if ($feature->exists) @method('PUT') @endif
    <label class="block text-sm font-medium">Name
        <input name="name" value="{{ old('name', $feature->name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('name')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-medium">Kind
        <select name="kind" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @foreach (\App\Enums\InfrastructureKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $feature->kind?->value) === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-medium">County
        <select name="county_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            <option value="">Not linked</option>
            @foreach ($counties as $county)
                <option value="{{ $county->id }}" @selected((string) old('county_id', $feature->county_id) === (string) $county->id)>{{ $county->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-medium">Place
        <select name="locality_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            <option value="">Not linked</option>
            @foreach ($places as $place)
                <option value="{{ $place->id }}" @selected((string) old('locality_id', $feature->locality_id) === (string) $place->id)>{{ $place->name }} ({{ $place->kind->label() }}@if ($place->county), {{ $place->county->name }}@endif)</option>
            @endforeach
        </select>
        @error('locality_id')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-medium">Source
        <input name="source" value="{{ old('source', $feature->source) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Where this reference was recorded">
        @error('source')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-medium">Notes
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('notes', $feature->notes) }}</textarea>
    </label>
    <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-medium">Latitude
            <input name="latitude" value="{{ old('latitude', $feature->latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium">Longitude
            <input name="longitude" value="{{ old('longitude', $feature->longitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
    </div>
    <label class="block text-sm font-medium">Verified boundary (WKT)
        <textarea name="boundary_wkt" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Leave empty. Do not invent a road or ward boundary.">{{ old('boundary_wkt', $feature->boundary_wkt) }}</textarea>
    </label>
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $feature->is_active ?? true))> Active</label>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save reference</button>
</form>
