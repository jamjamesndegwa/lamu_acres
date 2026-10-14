@props([
    'embed' => null,
    'latitude' => null,
    'longitude' => null,
    'empty' => 'No public coordinates have been recorded.',
])

@if (filled($embed))
    <iframe title="Approximate area map" class="mt-4 h-72 w-full rounded-2xl border border-sand-200" src="{{ $embed }}" loading="lazy"></iframe>
@elseif ($latitude === null || $longitude === null || $latitude === '' || $longitude === '')
    <x-state title="No map" :message="$empty" />
@else
    @once
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @endonce
    <div
        data-shared-map
        data-point-a="{{ $latitude }}"
        data-point-b="{{ $longitude }}"
        class="mt-3 h-64 overflow-hidden rounded-2xl border border-sand-200"
        role="img"
        aria-label="Map of the published location"
    ></div>
    @once
        <script>
            document.querySelectorAll('[data-shared-map]').forEach((node) => {
                if (!window.L || node.dataset.ready === '1') {
                    return
                }

                const north = Number(node.dataset.pointA)
                const east = Number(node.dataset.pointB)
                const map = L.map(node, { scrollWheelZoom: false }).setView([north, east], 13)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap',
                }).addTo(map)
                L.marker([north, east]).addTo(map)
                node.dataset.ready = '1'
            })
        </script>
    @endonce
@endif
