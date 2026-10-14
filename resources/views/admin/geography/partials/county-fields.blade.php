<form method="POST" action="{{ $route }}" data-turbo-frame="_top" data-confirm="Save this county?" class="space-y-4">
    @csrf
    @if ($county->exists) @method('PUT') @endif
    <label class="block text-sm font-medium">Name
        <input name="name" value="{{ old('name', $county->name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('name')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-medium">Code
        <input name="code" value="{{ old('code', $county->code) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('code')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-medium">Latitude
            <input name="latitude" value="{{ old('latitude', $county->latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium">Longitude
            <input name="longitude" value="{{ old('longitude', $county->longitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
    </div>
    <label class="block text-sm font-medium">Verified boundary (WKT)
        <textarea name="boundary_wkt" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Leave empty unless a verified boundary file is available">{{ old('boundary_wkt', $county->boundary_wkt) }}</textarea>
    </label>
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $county->is_active ?? true))> Active</label>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save county</button>
</form>
