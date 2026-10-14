<form method="POST" action="{{ $route }}" data-turbo-frame="_top" data-confirm="Save this place?" class="space-y-4">
    @csrf
    @if ($locality->exists) @method('PUT') @endif
    <label class="block text-sm font-medium">Name
        <input name="name" value="{{ old('name', $locality->name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
    </label>
    <label class="block text-sm font-medium">Kind
        <select name="kind" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @foreach (\App\Enums\LocalityKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $locality->kind?->value) === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-medium">Parent
        <select name="parent_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            <option value="">None</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" @selected((string) old('parent_id', $locality->parent_id) === (string) $parent->id)>{{ $parent->name }}</option>
            @endforeach
        </select>
    </label>
    <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-medium">Latitude
            <input name="latitude" value="{{ old('latitude', $locality->latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium">Longitude
            <input name="longitude" value="{{ old('longitude', $locality->longitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
    </div>
    <label class="block text-sm font-medium">Verified boundary (WKT)
        <textarea name="boundary_wkt" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Leave empty. Do not invent a ward boundary.">{{ old('boundary_wkt', $locality->boundary_wkt) }}</textarea>
    </label>
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $locality->is_active ?? true))> Active</label>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save place</button>
</form>

@if ($locality->exists)
    <form method="POST" action="{{ route('admin.geography.aliases.store', $locality) }}" data-turbo-frame="_top" class="mt-6 space-y-3 border-t border-sand-200 pt-4">
        @csrf
        <label class="block text-sm font-medium">Alias
            <input name="name" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Add alias</button>
    </form>
@endif
