@props([
    'markers' => [],
    'fallbackLatitude' => -2.27,
    'fallbackLongitude' => 40.9,
    'fallbackZoom' => 9,
    'count' => 0,
])

@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" data-land-directory-leaflet="true">
@endonce

<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-lg border border-sand-200 bg-sand-50']) }}>
    <script type="application/json" id="land-directory-map-markers">@json($markers)</script>
    <div
        id="land-directory-map"
        class="h-96 w-full min-h-[16rem]"
        role="img"
        aria-label="Map of published listings matching your filters"
        data-fallback-lat="{{ $fallbackLatitude }}"
        data-fallback-lng="{{ $fallbackLongitude }}"
        data-fallback-zoom="{{ $fallbackZoom }}"
    ></div>
    <p id="land-directory-map-status" class="border-t border-sand-200 bg-white px-3 py-2 text-xs text-stone-600" role="status">
        @if ($count === 0)
            No listings to show on the map for these filters.
        @else
            {{ $count }} {{ \Illuminate\Support\Str::plural('listing', $count) }} on the map
            @if (count($markers) < $count)
                ({{ count($markers) }} with a public map point)
            @endif
            . Green dots are approximate locations, not parcel boundaries.
        @endif
    </p>
</div>

@once
    <script data-land-directory-map-script="true">
        (function () {
            function validPoint(lat, lng) {
                return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
            }

            function readMarkers() {
                const payload = document.getElementById('land-directory-map-markers');
                if (!payload || payload.textContent.trim() === '') {
                    return [];
                }

                try {
                    const parsed = JSON.parse(payload.textContent);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (error) {
                    return [];
                }
            }

            function ensureLeafletCss() {
                if (document.querySelector('link[data-land-directory-leaflet]')) {
                    return Promise.resolve();
                }

                return new Promise(function (resolve) {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    link.dataset.landDirectoryLeaflet = 'true';
                    link.onload = function () { resolve(); };
                    link.onerror = function () { resolve(); };
                    document.head.appendChild(link);
                });
            }

            function ensureLeafletJs() {
                if (window.L) {
                    return Promise.resolve();
                }

                const existing = document.querySelector('script[data-land-directory-leaflet]');
                if (existing) {
                    return new Promise(function (resolve) {
                        existing.addEventListener('load', function () { resolve(); }, { once: true });
                    });
                }

                return new Promise(function (resolve, reject) {
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    script.dataset.landDirectoryLeaflet = 'true';
                    script.onload = function () { resolve(); };
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }

            function loadLeaflet() {
                return ensureLeafletCss().then(ensureLeafletJs);
            }

            function destroyLandDirectoryMap(node) {
                if (!node) {
                    return;
                }

                const map = node._leaflet_map;
                if (map) {
                    map.remove();
                    delete node._leaflet_map;
                }

                delete node.dataset.ready;
            }

            function focusMap(map, plotted, fallbackLat, fallbackLng, fallbackZoom) {
                if (plotted.length > 1) {
                    map.fitBounds(L.featureGroup(plotted).getBounds().pad(0.15), { maxZoom: 13 });
                } else if (plotted.length === 1) {
                    map.setView(plotted[0].getLatLng(), 13);
                } else if (validPoint(fallbackLat, fallbackLng)) {
                    map.setView([fallbackLat, fallbackLng], fallbackZoom);
                } else {
                    map.setView([-2.27, 40.9], 9);
                }

                map.invalidateSize({ animate: false });
            }

            function initLandDirectoryMap(node) {
                if (!node || typeof window.L === 'undefined') {
                    return;
                }

                destroyLandDirectoryMap(node);

                const markers = readMarkers();
                const fallbackLat = Number(node.dataset.fallbackLat);
                const fallbackLng = Number(node.dataset.fallbackLng);
                const fallbackZoom = Number(node.dataset.fallbackZoom) || 9;

                const map = L.map(node, { scrollWheelZoom: true, preferCanvas: true });
                node._leaflet_map = map;
                node.dataset.ready = '1';

                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 18,
                    referrerPolicy: 'origin',
                }).addTo(map);

                const plotted = [];

                markers.forEach(function (item) {
                    const lat = Number(item.latitude);
                    const lng = Number(item.longitude);
                    if (!validPoint(lat, lng)) {
                        return;
                    }

                    const popup = document.createElement('div');
                    const link = document.createElement('a');
                    link.textContent = item.title || 'View listing';
                    link.href = item.url || '#';
                    link.className = 'font-medium text-lamu-800 hover:underline';
                    popup.appendChild(link);

                    const marker = L.circleMarker([lat, lng], {
                        radius: 9,
                        color: '#143028',
                        weight: 2,
                        fillColor: '#22a066',
                        fillOpacity: 1,
                    }).addTo(map).bindPopup(popup);

                    plotted.push(marker);
                });

                const status = document.getElementById('land-directory-map-status');
                if (status && plotted.length === 0 && markers.length > 0) {
                    status.textContent = 'Listings matched your filters, but none have public map coordinates yet.';
                }

                function refreshView() {
                    if (node.offsetWidth < 32 || node.offsetHeight < 32) {
                        return;
                    }

                    focusMap(map, plotted, fallbackLat, fallbackLng, fallbackZoom);
                }

                map.whenReady(function () {
                    refreshView();
                    window.setTimeout(refreshView, 100);
                    window.setTimeout(refreshView, 400);
                });

                if (typeof ResizeObserver !== 'undefined') {
                    const observer = new ResizeObserver(function () {
                        refreshView();
                    });
                    observer.observe(node);
                    map.on('unload', function () {
                        observer.disconnect();
                    });
                }
            }

            function boot() {
                const node = document.getElementById('land-directory-map');
                if (!node) {
                    return;
                }

                loadLeaflet()
                    .then(function () {
                        initLandDirectoryMap(node);
                    })
                    .catch(function () {
                        const status = document.getElementById('land-directory-map-status');
                        if (status) {
                            status.textContent = 'The map could not load. Listing results below are still available.';
                        }
                    });
            }

            document.addEventListener('DOMContentLoaded', boot);
            document.addEventListener('turbo:load', boot);
            document.addEventListener('turbo:frame-load', boot);
            document.addEventListener('turbo:before-cache', function () {
                destroyLandDirectoryMap(document.getElementById('land-directory-map'));
            });
        })();
    </script>
@endonce
