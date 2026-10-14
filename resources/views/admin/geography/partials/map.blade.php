@php
    $drawnBoundaries = collect($features)->contains(fn (array $feature): bool => ($feature['ring'] ?? []) !== []);
    $storedButUndrawn = collect($features)->contains(fn (array $feature): bool => ($feature['boundaryStored'] ?? false) && ($feature['ring'] ?? []) === []);
@endphp

<section class="mt-8" aria-labelledby="geography-map-heading">
    <h2 id="geography-map-heading" class="font-semibold text-lamu-900">Map preview</h2>
    @if ($features === [])
        <p class="mt-3 rounded-2xl border border-dashed border-sand-300 bg-white p-5 text-sm text-stone-700">No verified boundary or named point is stored. Nothing is drawn, because a missing boundary is not replaced with an estimated outline.</p>
    @else
        <p class="mt-3 text-sm text-stone-600">
            @if ($drawnBoundaries)
                Solid shapes are verified boundaries already stored on the record.
            @else
                No verified boundary is stored. Markers are named points only, not administrative borders.
            @endif
            @if ($storedButUndrawn)
                A stored boundary value could not be drawn and was left off the map.
            @endif
        </p>
        <div id="geography-map" class="mt-4 h-80 overflow-hidden rounded-2xl border border-sand-200"></div>
        <ul class="mt-3 flex flex-wrap gap-4 text-xs text-stone-600">
            <li>Named point</li>
            <li>Verified boundary, when stored</li>
            <li>Missing boundary stays off the map</li>
        </ul>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (function () {
                const features = @json($features);
                const element = document.getElementById('geography-map');
                if (!element || typeof L === 'undefined' || element.dataset.ready === 'true') {
                    return;
                }
                element.dataset.ready = 'true';
                const map = L.map(element);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);
                const group = L.featureGroup();
                features.forEach(function (feature) {
                    if (feature.ring && feature.ring.length > 0) {
                        L.polygon(feature.ring.map(function (point) {
                            return [point.lat, point.lng];
                        }), { color: '#143028' }).bindPopup(feature.name + ' · ' + (feature.note || 'verified boundary')).addTo(group);
                    }
                    if (feature.lat !== null && feature.lng !== null) {
                        const note = feature.note || (feature.boundaryStored ? 'Named point' : 'Named point · boundary not stored');
                        L.circleMarker([feature.lat, feature.lng], { radius: 6, color: '#a68548' }).bindPopup(feature.name + ' · ' + note).addTo(group);
                    }
                });
                group.addTo(map);
                if (group.getLayers().length > 0) {
                    map.fitBounds(group.getBounds().pad(0.2));
                }
                setTimeout(function () { map.invalidateSize(); }, 0);
            })();
        </script>
    @endif
</section>
