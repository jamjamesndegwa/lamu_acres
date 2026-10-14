@php
    $current = $sourcingRequest ?? null;
    $selectedMandatory = old('mandatory', $current?->amenities->where('required', true)->map(fn ($amenity) => $amenity->amenity->value)->all() ?? []);
    $selectedPreferred = old('preferred', $current?->amenities->where('required', false)->map(fn ($amenity) => $amenity->amenity->value)->all() ?? []);
    $polygonText = old('polygon_text');
    if ($polygonText === null && $current) {
        $polygonText = collect($current->boundary_points ?? [])->map(fn (array $point) => $point['lat'].', '.$point['lng'])->implode("\n");
    }
@endphp

<form method="POST" action="{{ $action }}" data-turbo-frame="_top" data-confirm="{{ $confirm }}" class="mt-6 max-w-3xl space-y-5">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="location_label" class="block text-sm font-medium">Preferred location</label>
        <input id="location_label" name="location_label" value="{{ old('location_label', $current?->location_label) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('location_label') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="centre_latitude" class="block text-sm font-medium">Map centre latitude</label>
            <input id="centre_latitude" name="centre_latitude" value="{{ old('centre_latitude', $current?->centre_latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('centre_latitude') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="centre_longitude" class="block text-sm font-medium">Map centre longitude</label>
            <input id="centre_longitude" name="centre_longitude" value="{{ old('centre_longitude', $current?->centre_longitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('centre_longitude') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="radius_km" class="block text-sm font-medium">Radius (km)</label>
            <input id="radius_km" name="radius_km" value="{{ old('radius_km', $current?->radius_km) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('radius_km') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="polygon_text" class="block text-sm font-medium">Drawn polygon</label>
        <textarea id="polygon_text" name="polygon_text" rows="4" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="-2.2800, 40.8900">{{ $polygonText }}</textarea>
        <p class="mt-1 text-xs text-stone-500">Optional. One latitude, longitude pair per line.</p>
        @error('polygon') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="min_acreage" class="block text-sm font-medium">Minimum acreage</label>
            <input id="min_acreage" name="min_acreage" value="{{ old('min_acreage', $current?->min_acreage) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('min_acreage') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="max_acreage" class="block text-sm font-medium">Maximum acreage</label>
            <input id="max_acreage" name="max_acreage" value="{{ old('max_acreage', $current?->max_acreage) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('max_acreage') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="maximum_budget" class="block text-sm font-medium">Maximum budget (KSh)</label>
            <input id="maximum_budget" name="maximum_budget" value="{{ old('maximum_budget', $current?->maximum_budget) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('maximum_budget') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="intended_use" class="block text-sm font-medium">Intended land use</label>
        <select id="intended_use" name="intended_use" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">No preference</option>
            @foreach ($landUses as $use)
                <option value="{{ $use->value }}" @selected(old('intended_use', $current?->intended_use?->value) === $use->value)>{{ $use->label() }}</option>
            @endforeach
        </select>
    </div>

    <fieldset>
        <legend class="text-sm font-medium">Mandatory amenities</legend>
        <div class="mt-2 flex flex-wrap gap-3">
            @foreach ($amenities as $amenity)
                <label class="text-sm"><input type="checkbox" name="mandatory[]" value="{{ $amenity->value }}" @checked(in_array($amenity->value, $selectedMandatory, true))> {{ $amenity->label() }}</label>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend class="text-sm font-medium">Preferred amenities</legend>
        <div class="mt-2 flex flex-wrap gap-3">
            @foreach ($amenities as $amenity)
                <label class="text-sm"><input type="checkbox" name="preferred[]" value="{{ $amenity->value }}" @checked(in_array($amenity->value, $selectedPreferred, true))> {{ $amenity->label() }}</label>
            @endforeach
        </div>
        @error('preferred') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </fieldset>

    <div>
        <label for="notes" class="block text-sm font-medium">Additional requirements</label>
        <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('notes', $current?->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">{{ $submit }}</button>
</form>
