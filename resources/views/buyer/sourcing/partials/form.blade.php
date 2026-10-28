@php
    $current = $sourcingRequest ?? null;
    $profileDefaults = $current === null ? ($profile ?? null) : null;
    $places = $places ?? [];
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" data-leaflet="true">

<form method="POST" action="{{ $action }}" data-turbo-frame="_top" data-confirm="{{ $confirm }}" class="mt-6 max-w-4xl space-y-8">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <section class="overflow-hidden rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Where do you want land?</h2>
                <p class="mt-1 max-w-2xl text-sm text-stone-700">Drop a pin on the map and set how far out to search. Coordinates are saved automatically — you do not need to type latitude or longitude.</p>
            </div>
            <button type="button" id="map-clear-pin" class="rounded-full border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-lamu-900 hover:border-lamu-700">Clear pin</button>
        </div>

        <p id="map-location-summary" role="status" class="mt-4 rounded-xl border border-sand-200 bg-sand-50 px-4 py-3 text-sm text-stone-800">
            Tap the map to place your search pin.
        </p>

        <div id="sourcing-map" class="mt-4 h-[22rem] overflow-hidden rounded-2xl border border-sand-200 ring-1 ring-sand-100 sm:h-[26rem]"></div>

        @if ($places !== [])
            <div class="mt-5">
                <label for="place-picker" class="block text-sm font-medium text-lamu-900">Jump to a known place</label>
                @php
                    $chosenPlace = trim((string) old('location_label', $current?->location_label ?? $profileDefaults?->preferred_locality));
                @endphp
                <select id="place-picker" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2 shadow-sm">
                    <option value="">Choose a place, or tap the map</option>
                    @foreach ($places as $place)
                        @php($placeLabel = $place->county ? $place->name.', '.$place->county->name : $place->name)
                        <option value="{{ $placeLabel }}" data-lat="{{ $place->latitude }}" data-lng="{{ $place->longitude }}" @selected(strcasecmp($chosenPlace, $placeLabel) === 0 || strcasecmp($chosenPlace, $place->name) === 0)>{{ $placeLabel }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="mt-4">
            <label for="location_label" class="block text-sm font-medium text-lamu-900">Area name</label>
            <input id="location_label" name="location_label" value="{{ old('location_label', $current?->location_label ?? $profileDefaults?->preferred_locality) }}" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2 shadow-sm" placeholder="e.g. Shela, Mokowe, Lamu Island">
            @error('location_label') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="mt-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label for="radius_km_slider" class="text-sm font-medium text-lamu-900">Search radius</label>
                <span id="radius_km_display" class="rounded-full bg-lamu-900 px-3 py-1 text-xs font-semibold text-white">5 km</span>
            </div>
            <input
                id="radius_km_slider"
                type="range"
                min="0.5"
                max="150"
                step="0.5"
                value="{{ old('radius_km', $current?->radius_km ?? 5) }}"
                class="mt-3 w-full accent-lamu-900"
                @disabled(! old('centre_latitude', $current?->centre_latitude))
            >
            <p class="mt-2 text-xs text-stone-600">Published listings within this distance of your pin are considered for matching.</p>
            @error('radius_km') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <input type="hidden" name="centre_latitude" id="centre_latitude" value="{{ old('centre_latitude', $current?->centre_latitude) }}">
        <input type="hidden" name="centre_longitude" id="centre_longitude" value="{{ old('centre_longitude', $current?->centre_longitude) }}">
        <input type="hidden" name="radius_km" id="radius_km" value="{{ old('radius_km', $current?->radius_km ?? '') }}">
        <textarea id="polygon_text" name="polygon_text" class="hidden" aria-hidden="true" tabindex="-1">{{ $polygonText }}</textarea>
        @error('centre_latitude') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
        @error('centre_longitude') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        @error('polygon') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </section>

    <section class="space-y-4 rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">Size and budget</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="min_acreage" class="block text-sm font-medium">Minimum acreage</label>
                <input id="min_acreage" name="min_acreage" value="{{ old('min_acreage', $current?->min_acreage ?? $profileDefaults?->min_acreage) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('min_acreage') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="max_acreage" class="block text-sm font-medium">Maximum acreage</label>
                <input id="max_acreage" name="max_acreage" value="{{ old('max_acreage', $current?->max_acreage ?? $profileDefaults?->max_acreage) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('max_acreage') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="maximum_budget" class="block text-sm font-medium">Total budget (KSh)</label>
                <input id="maximum_budget" name="maximum_budget" value="{{ old('maximum_budget', $current?->maximum_budget ?? $profileDefaults?->budget_amount) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
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

<script>
    (function () {
        const mapElement = document.getElementById('sourcing-map');
        if (!mapElement || mapElement.dataset.ready === 'loading' || mapElement.dataset.ready === 'true') {
            return;
        }
        mapElement.dataset.ready = 'loading';

        function ensureLeaflet(done) {
            if (!document.querySelector('link[data-leaflet]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                link.dataset.leaflet = 'true';
                document.head.appendChild(link);
            }
            if (typeof L !== 'undefined') {
                done();
                return;
            }
            const existing = document.querySelector('script[data-leaflet]');
            if (existing) {
                existing.addEventListener('load', done);
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.dataset.leaflet = 'true';
            script.onload = done;
            script.onerror = function () {
                mapElement.dataset.ready = 'failed';
                mapElement.textContent = 'The map could not be loaded. Check the connection and open the search again.';
            };
            document.head.appendChild(script);
        }

        ensureLeaflet(function () {
            if (mapElement.dataset.ready === 'true' || typeof L === 'undefined') {
                return;
            }
            mapElement.dataset.ready = 'true';
            startMap();
        });

        function startMap() {
        const latitude = document.getElementById('centre_latitude');
        const longitude = document.getElementById('centre_longitude');
        const radius = document.getElementById('radius_km');
        const radiusSlider = document.getElementById('radius_km_slider');
        const radiusDisplay = document.getElementById('radius_km_display');
        const summary = document.getElementById('map-location-summary');
        const polygon = document.getElementById('polygon_text');
        const clearPin = document.getElementById('map-clear-pin');
        const picker = document.getElementById('place-picker');
        const label = document.getElementById('location_label');
        const startLat = Number(latitude.value) || -2.269;
        const startLng = Number(longitude.value) || 40.902;
        const map = L.map(mapElement, { scrollWheelZoom: true }).setView([startLat, startLng], latitude.value ? 12 : 11);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        let marker = null;
        let circle = null;

        function hasPin() {
            return latitude.value !== '' && longitude.value !== '';
        }

        function currentRadiusKm() {
            const value = Number(radiusSlider.value);
            return Number.isNaN(value) || value <= 0 ? 5 : value;
        }

        function syncRadiusFields() {
            const kilometres = currentRadiusKm();
            radius.value = kilometres.toFixed(1).replace(/\.0$/, '');
            radiusDisplay.textContent = kilometres + ' km';
        }

        function updateSummary() {
            if (! hasPin()) {
                summary.textContent = 'Tap the map to place your search pin.';
                summary.classList.remove('border-lamu-300', 'bg-lamu-50');
                summary.classList.add('border-sand-200', 'bg-sand-50');
                radiusSlider.disabled = true;
                return;
            }

            radiusSlider.disabled = false;
            syncRadiusFields();
            const place = label.value.trim() || 'Selected map point';
            summary.textContent = 'Searching within ' + radiusDisplay.textContent + ' of ' + place + '.';
            summary.classList.remove('border-sand-200', 'bg-sand-50');
            summary.classList.add('border-lamu-300', 'bg-lamu-50');
        }

        function setPin(lat, lng, options) {
            options = options || {};
            latitude.value = lat.toFixed(6);
            longitude.value = lng.toFixed(6);
            if (options.clearPolygon !== false) {
                polygon.value = '';
            }
            if (! radius.value) {
                radiusSlider.value = '5';
            }
            syncRadiusFields();
            draw();
            updateSummary();
            if (options.moveMap !== false) {
                map.setView([lat, lng], Math.max(map.getZoom(), 12));
            }
        }

        function clearPinSelection() {
            latitude.value = '';
            longitude.value = '';
            polygon.value = '';
            draw();
            updateSummary();
        }

        function draw() {
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }
            if (circle) {
                map.removeLayer(circle);
                circle = null;
            }

            const lat = Number(latitude.value);
            const lng = Number(longitude.value);
            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                return;
            }

            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                const position = marker.getLatLng();
                setPin(position.lat, position.lng, { moveMap: false, clearPolygon: true });
                const near = nearestPlace(position.lat, position.lng);
                if (near && picker) {
                    picker.value = near.value;
                    label.value = near.value;
                    updateSummary();
                }
            });

            const kilometres = currentRadiusKm();
            circle = L.circle([lat, lng], {
                radius: kilometres * 1000,
                color: '#143028',
                weight: 2,
                fillColor: '#245744',
                fillOpacity: 0.15,
            }).addTo(map);
            map.fitBounds(circle.getBounds(), { padding: [24, 24], maxZoom: 14 });
        }

        map.on('click', function (event) {
            setPin(event.latlng.lat, event.latlng.lng);
            const near = nearestPlace(event.latlng.lat, event.latlng.lng);
            if (near) {
                picker.value = near.value;
                label.value = near.value;
            } else if (! label.value.trim()) {
                label.value = 'Map selection';
            }
            updateSummary();
        });

        function placeDistance(lat1, lng1, lat2, lng2) {
            const toRad = function (value) { return value * Math.PI / 180; };
            const dLat = toRad(lat2 - lat1);
            const dLng = toRad(lng2 - lng1);
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
            return 6371 * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
        }

        function nearestPlace(lat, lng) {
            if (!picker) {
                return null;
            }
            let best = null;
            let bestKm = 25;
            Array.from(picker.options).forEach(function (option) {
                if (!option.dataset.lat) {
                    return;
                }
                const kilometres = placeDistance(lat, lng, Number(option.dataset.lat), Number(option.dataset.lng));
                if (kilometres < bestKm) {
                    best = option;
                    bestKm = kilometres;
                }
            });
            return best;
        }

        function selectNamedPlace() {
            if (!picker || !label) {
                return;
            }
            const current = label.value.trim().toLowerCase();
            if (current === '') {
                return;
            }
            const match = Array.from(picker.options).find(function (option) {
                const name = option.value.trim().toLowerCase();
                return name !== '' && (current === name || current === name.split(',')[0].trim() || name.startsWith(current));
            });
            if (match) {
                picker.value = match.value;
            }
        }

        if (picker) {
            picker.addEventListener('change', function () {
                const option = picker.selectedOptions[0];
                if (!option || !option.dataset.lat) {
                    return;
                }
                label.value = option.value;
                setPin(Number(option.dataset.lat), Number(option.dataset.lng));
                updateSummary();
            });
        }

        radiusSlider.addEventListener('input', function () {
            syncRadiusFields();
            draw();
            updateSummary();
        });

        if (label) {
            label.addEventListener('input', updateSummary);
        }

        if (clearPin) {
            clearPin.addEventListener('click', clearPinSelection);
        }

        if (radius.value) {
            radiusSlider.value = radius.value;
        }
        selectNamedPlace();
        if (picker && !picker.value && hasPin()) {
            const near = nearestPlace(Number(latitude.value), Number(longitude.value));
            if (near) {
                picker.value = near.value;
                if (!label.value) {
                    label.value = near.value;
                }
            }
        }
        draw();
        updateSummary();

        function refreshMap() {
            map.invalidateSize();
        }
        setTimeout(refreshMap, 0);
        setTimeout(refreshMap, 300);
        }
    })();
</script>
