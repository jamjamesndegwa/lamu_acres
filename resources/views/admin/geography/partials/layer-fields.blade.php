<form method="POST" action="{{ $route }}" data-turbo-frame="_top" data-confirm="Save this map layer?" class="space-y-4">
    @csrf
    @if ($layer->exists) @method('PUT') @endif
    <label class="block text-sm font-medium">Name
        <input name="name" value="{{ old('name', $layer->name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('name')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-medium">Kind
        <select name="kind" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @foreach (\App\Enums\MapLayerKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $layer->kind?->value) === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-medium">Source
        <input name="source" value="{{ old('source', $layer->source) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('source')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-medium">Description
        <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('description', $layer->description) }}</textarea>
    </label>
    <label class="block text-sm font-medium">Limit to a county
        <select name="county_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            <option value="">All recorded counties</option>
            @foreach ($counties as $county)
                <option value="{{ $county->id }}" @selected((string) old('county_id', $layer->county_id) === (string) $county->id)>{{ $county->name }}</option>
            @endforeach
        </select>
    </label>
    <p class="text-sm text-stone-600">Coordinates and a boundary are kept only on a custom overlay. County, place, and infrastructure layers read the records already stored. An empty boundary is not filled in.</p>
    <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-medium">Latitude
            <input name="latitude" value="{{ old('latitude', $layer->latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium">Longitude
            <input name="longitude" value="{{ old('longitude', $layer->longitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
    </div>
    <label class="block text-sm font-medium">Verified boundary (WKT)
        <textarea name="boundary_wkt" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Leave empty unless a verified boundary file is available">{{ old('boundary_wkt', $layer->boundary_wkt) }}</textarea>
    </label>
    <input type="hidden" name="is_visible" value="0">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $layer->is_visible ?? true))> Show on the map</label>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save layer</button>
</form>
