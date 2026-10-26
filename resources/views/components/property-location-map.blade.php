@props([
    'publicLat' => null,
    'publicLng' => null,
    'restrictedLat' => null,
    'restrictedLng' => null,
    'defaultLat' => -2.269,
    'defaultLng' => 40.902,
    'zoom' => 11,
])

<div class="space-y-3" data-property-location-map>
    <p class="text-sm text-stone-600">Click the map to place markers. The approximate point is shown to buyers; the exact point is for staff and verification only.</p>
    <div class="grid gap-4 lg:grid-cols-2">
        <div>
            <p class="text-sm font-medium text-lamu-900">Approximate location (public)</p>
            <div id="property-map-public" class="mt-2 h-64 w-full rounded-xl border border-sand-200 bg-sand-50" role="application" aria-label="Map to choose approximate land location"></div>
            <input type="hidden" name="public_latitude" id="public_latitude" value="{{ old('public_latitude', $publicLat) }}">
            <input type="hidden" name="public_longitude" id="public_longitude" value="{{ old('public_longitude', $publicLng) }}">
            <p class="mt-1 text-xs text-stone-500" data-public-coords-label>Not set</p>
        </div>
        <div>
            <p class="text-sm font-medium text-lamu-900">Exact location (staff only)</p>
            <div id="property-map-exact" class="mt-2 h-64 w-full rounded-xl border border-sand-200 bg-sand-50" role="application" aria-label="Map to choose exact land location"></div>
            <input type="hidden" name="restricted_latitude" id="restricted_latitude" value="{{ old('restricted_latitude', $restrictedLat) }}">
            <input type="hidden" name="restricted_longitude" id="restricted_longitude" value="{{ old('restricted_longitude', $restrictedLng) }}">
            <p class="mt-1 text-xs text-stone-500" data-exact-coords-label>Not set</p>
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm text-stone-700">
        <input type="checkbox" data-copy-exact-to-public @checked(old('public_latitude', $publicLat) && old('public_latitude', $publicLat) == old('restricted_latitude', $restrictedLat))>
        Use the exact point as the public approximate location
    </label>
</div>

<script>
    (function () {
        const root = document.querySelector('[data-property-location-map]');
        if (!root || root.dataset.ready === 'true') {
            return;
        }
        root.dataset.ready = 'loading';

        const publicLat = document.getElementById('public_latitude');
        const publicLng = document.getElementById('public_longitude');
        const exactLat = document.getElementById('restricted_latitude');
        const exactLng = document.getElementById('restricted_longitude');
        const publicLabel = root.querySelector('[data-public-coords-label]');
        const exactLabel = root.querySelector('[data-exact-coords-label]');
        const copyToggle = root.querySelector('[data-copy-exact-to-public]');
        const startLat = Number(exactLat.value || publicLat.value) || {{ (float) $defaultLat }};
        const startLng = Number(exactLng.value || publicLng.value) || {{ (float) $defaultLng }};

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
            document.head.appendChild(script);
        }

        function label(node, lat, lng) {
            if (!node) {
                return;
            }
            node.textContent = lat && lng ? lat.toFixed(6) + ', ' + lng.toFixed(6) : 'Not set';
        }

        function bindMap(elementId, latInput, lngInput, labelNode, color) {
            const element = document.getElementById(elementId);
            const map = L.map(element, { scrollWheelZoom: true }).setView([startLat, startLng], {{ (int) $zoom }});
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            let marker = null;
            const place = function (lat, lng) {
                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);
                label(labelNode, lat, lng);
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                    marker.on('dragend', function () {
                        const point = marker.getLatLng();
                        place(point.lat, point.lng);
                        if (copyToggle?.checked) {
                            publicLat.value = latInput.value;
                            publicLng.value = lngInput.value;
                            label(publicLabel, point.lat, point.lng);
                        }
                    });
                }
            };

            if (latInput.value && lngInput.value) {
                place(Number(latInput.value), Number(lngInput.value));
            }

            map.on('click', function (event) {
                place(event.latlng.lat, event.latlng.lng);
                if (copyToggle?.checked && latInput === exactLat) {
                    publicLat.value = latInput.value;
                    publicLng.value = lngInput.value;
                    label(publicLabel, event.latlng.lat, event.latlng.lng);
                }
            });

            return { place, map };
        }

        ensureLeaflet(function () {
            root.dataset.ready = 'true';
            const exact = bindMap('property-map-exact', exactLat, exactLng, exactLabel, '#143028');
            bindMap('property-map-public', publicLat, publicLng, publicLabel, '#245744');

            copyToggle?.addEventListener('change', function () {
                if (copyToggle.checked && exactLat.value && exactLng.value) {
                    publicLat.value = exactLat.value;
                    publicLng.value = exactLng.value;
                    label(publicLabel, Number(publicLat.value), Number(publicLng.value));
                }
            });

            label(publicLabel, Number(publicLat.value), Number(publicLng.value));
            label(exactLabel, Number(exactLat.value), Number(exactLng.value));
        });
    })();
</script>
