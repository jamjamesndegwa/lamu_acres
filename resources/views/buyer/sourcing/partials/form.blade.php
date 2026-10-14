@php
    $current = $sourcingRequest ?? null;
    $selectedMandatory = old('mandatory', $current?->amenities->where('required', true)->map(fn ($amenity) => $amenity->amenity->value)->all() ?? []);
    $selectedPreferred = old('preferred', $current?->amenities->where('required', false)->map(fn ($amenity) => $amenity->amenity->value)->all() ?? []);
    $polygonText = old('polygon_text');
    if ($polygonText === null && $current) {
        $polygonText = collect($current->boundary_points ?? [])->map(fn (array $point) => $point['lat'].', '.$point['lng'])->implode("\n");
    }
    $infrastructure = ['road_access', 'water', 'electricity', 'borehole'];
    $otherAmenities = collect($amenities)->reject(fn ($amenity) => in_array($amenity->value, $infrastructure, true));
    $infrastructureAmenities = collect($amenities)->filter(fn ($amenity) => in_array($amenity->value, $infrastructure, true));
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<form method="POST" action="{{ $action }}" data-turbo-frame="_top" data-confirm="{{ $confirm }}" class="mt-6 max-w-3xl space-y-8">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <section class="space-y-4">
        <h2 class="font-semibold text-lamu-900">Location</h2>
        <p class="text-sm text-stone-600">Click the map to place the search, set a radius, or draw an area. The same fields are sent to the sourcing request.</p>
        <div id="sourcing-map" class="h-80 overflow-hidden rounded-2xl border border-sand-200"></div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="map-mode-radius" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Radius</button>
            <button type="button" id="map-mode-area" class="rounded-full border border-sand-200 px-4 py-2 text-sm font-medium">Draw area</button>
            <button type="button" id="map-clear-area" class="rounded-full border border-sand-200 px-4 py-2 text-sm font-medium">Clear area</button>
        </div>
        <div>
            <label for="location_label" class="block text-sm font-medium">Place name</label>
            <input id="location_label" name="location_label" value="{{ old('location_label', $current?->location_label) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Shela, Lamu">
            @error('location_label') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="centre_latitude" class="block text-sm font-medium">Latitude</label>
                <input id="centre_latitude" name="centre_latitude" value="{{ old('centre_latitude', $current?->centre_latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('centre_latitude') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="centre_longitude" class="block text-sm font-medium">Longitude</label>
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
            <label for="polygon_text" class="block text-sm font-medium">Drawn area</label>
            <textarea id="polygon_text" name="polygon_text" rows="4" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="-2.2800, 40.8900">{{ $polygonText }}</textarea>
            <p class="mt-1 text-xs text-stone-500">One latitude, longitude pair per line. Three points close the area.</p>
            @error('polygon') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="font-semibold text-lamu-900">Size and budget</h2>
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
                <label for="maximum_budget" class="block text-sm font-medium">Total budget (KSh)</label>
                <input id="maximum_budget" name="maximum_budget" value="{{ old('maximum_budget', $current?->maximum_budget) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('maximum_budget') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section>
        <label for="intended_use" class="block text-sm font-medium">Intended use</label>
        <select id="intended_use" name="intended_use" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">No preference</option>
            @foreach ($landUses as $use)
                <option value="{{ $use->value }}" @selected(old('intended_use', $current?->intended_use?->value) === $use->value)>{{ $use->label() }}</option>
            @endforeach
        </select>
    </section>

    <section class="space-y-4">
        <h2 class="font-semibold text-lamu-900">Road access and infrastructure</h2>
        <p class="text-sm text-stone-600">Mark what the land must have, and what you would prefer.</p>
        @foreach (['mandatory' => 'Required', 'preferred' => 'Preferred'] as $name => $legend)
            <fieldset>
                <legend class="text-sm font-medium">{{ $legend }}</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($infrastructureAmenities as $amenity)
                        <label class="text-sm">
                            <input type="checkbox" name="{{ $name }}[]" value="{{ $amenity->value }}" @checked(in_array($amenity->value, $name === 'mandatory' ? $selectedMandatory : $selectedPreferred, true))>
                            {{ $amenity->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </section>

    <section class="space-y-4">
        <h2 class="font-semibold text-lamu-900">Other amenities</h2>
        @foreach (['mandatory' => 'Mandatory', 'preferred' => 'Preferred'] as $name => $legend)
            <fieldset>
                <legend class="text-sm font-medium">{{ $legend }}</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($otherAmenities as $amenity)
                        <label class="text-sm">
                            <input type="checkbox" name="{{ $name }}[]" value="{{ $amenity->value }}" @checked(in_array($amenity->value, $name === 'mandatory' ? $selectedMandatory : $selectedPreferred, true))>
                            {{ $amenity->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        @error('preferred') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </section>

    <section>
        <label for="notes" class="block text-sm font-medium">Additional notes and location constraints</label>
        <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="For example, keep clear of the mangrove edge.">{{ old('notes', $current?->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </section>

    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">{{ $submit }}</button>
</form>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (function () {
        const mapElement = document.getElementById('sourcing-map');
        if (!mapElement || typeof L === 'undefined' || mapElement.dataset.ready === 'true') {
            return;
        }
        mapElement.dataset.ready = 'true';

        const latitude = document.getElementById('centre_latitude');
        const longitude = document.getElementById('centre_longitude');
        const radius = document.getElementById('radius_km');
        const polygon = document.getElementById('polygon_text');
        const startLat = Number(latitude.value) || -2.269;
        const startLng = Number(longitude.value) || 40.902;
        const map = L.map(mapElement).setView([startLat, startLng], 12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        let mode = 'radius';
        let marker = null;
        let circle = null;
        let shape = null;
        let points = [];

        function readPolygon() {
            return polygon.value.split(/\n/).map(function (line) {
                const parts = line.trim().split(/[,\s]+/);
                if (parts.length < 2 || parts[0] === '' || parts[1] === '') {
                    return null;
                }
                return L.latLng(Number(parts[0]), Number(parts[1]));
            }).filter(Boolean);
        }

        function writePolygon() {
            polygon.value = points.map(function (point) {
                return point.lat.toFixed(6) + ', ' + point.lng.toFixed(6);
            }).join('\n');
        }

        function draw() {
            if (marker) {
                map.removeLayer(marker);
            }
            if (circle) {
                map.removeLayer(circle);
            }
            if (shape) {
                map.removeLayer(shape);
            }
            const lat = Number(latitude.value);
            const lng = Number(longitude.value);
            if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                marker = L.marker([lat, lng]).addTo(map);
                const kilometres = Number(radius.value);
                if (!Number.isNaN(kilometres) && kilometres > 0) {
                    circle = L.circle([lat, lng], { radius: kilometres * 1000, color: '#143028' }).addTo(map);
                }
            }
            if (points.length > 0) {
                shape = L.polygon(points, { color: '#a68548' }).addTo(map);
            }
        }

        map.on('click', function (event) {
            if (mode === 'area') {
                points.push(event.latlng);
                writePolygon();
            } else {
                latitude.value = event.latlng.lat.toFixed(6);
                longitude.value = event.latlng.lng.toFixed(6);
                if (!radius.value) {
                    radius.value = '5';
                }
            }
            draw();
        });

        document.getElementById('map-mode-radius').addEventListener('click', function () {
            mode = 'radius';
        });
        document.getElementById('map-mode-area').addEventListener('click', function () {
            mode = 'area';
        });
        document.getElementById('map-clear-area').addEventListener('click', function () {
            points = [];
            polygon.value = '';
            draw();
        });
        [latitude, longitude, radius, polygon].forEach(function (field) {
            field.addEventListener('change', function () {
                points = readPolygon();
                draw();
            });
        });

        points = readPolygon();
        draw();
        setTimeout(function () { map.invalidateSize(); }, 0);
    })();
</script>
