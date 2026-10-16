<script>
(() => {
    const form = document.getElementById('find-land-form');
    if (!form) return;
    const latitude = document.getElementById('finder-latitude');
    const longitude = document.getElementById('finder-longitude');
    const mode = document.getElementById('finder-mode');
    const mapStatus = document.getElementById('finder-map-status');
    const validPoint = (lat, lng) => Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
    const selectPoint = (lat, lng) => {
        if (!validPoint(lat, lng)) return false;
        latitude.value = lat.toFixed(6);
        longitude.value = lng.toFixed(6);
        mode.value = 'nearby';
        return true;
    };
    const county = document.getElementById('finder-county');
    const locality = document.getElementById('finder-locality');
    const updateLocalities = () => {
        locality.querySelectorAll('optgroup').forEach((group) => {
            const excluded = county.value !== '' && group.dataset.county !== county.value;
            group.hidden = excluded;
            group.disabled = excluded;
            if (excluded && [...group.children].some((option) => option.selected)) locality.value = '';
        });
    };
    county.addEventListener('change', updateLocalities);
    updateLocalities();

    const centre = {{ \Illuminate\Support\Js::from($centre) }};
    const markers = {{ \Illuminate\Support\Js::from($markers) }};
    const registeredPoint = {{ \Illuminate\Support\Js::from(['latitude' => ($locality ?? $county)?->latitude, 'longitude' => ($locality ?? $county)?->longitude]) }};
    let map, searchPin, searchCircle;
    const clearPoint = () => {
        latitude.value = '';
        longitude.value = '';
        mode.value = 'area';
        if (form.elements.sort.value === 'nearest') form.elements.sort.value = 'newest';
        if (searchPin) { searchPin.remove(); searchPin = null; }
        if (searchCircle) { searchCircle.remove(); searchCircle = null; }
        mapStatus.textContent = 'Map selection cleared. Select a county or locality, then press Find land.';
    };
    document.getElementById('finder-clear-point').addEventListener('click', clearPoint);
    mode.addEventListener('change', () => { if (mode.value === 'area') clearPoint(); });
    const loadLeaflet = () => new Promise((resolve, reject) => {
        if (window.L) { resolve(); return; }
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(css);
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
    const initializeMap = async () => {
        mapStatus.textContent = 'Loading area map…';
        try {
            await loadLeaflet();
            const registered = registeredPoint.latitude !== null && registeredPoint.longitude !== null && validPoint(Number(registeredPoint.latitude), Number(registeredPoint.longitude));
            const start = centre ? [centre.latitude, centre.longitude] : registered ? [Number(registeredPoint.latitude), Number(registeredPoint.longitude)] : markers.length ? [markers[0].latitude, markers[0].longitude] : [0, 37];
            map = L.map('finder-map', { scrollWheelZoom: false }).setView(start, centre || registered || markers.length ? 11 : 6);
            const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 18,
                referrerPolicy: 'origin',
            }).addTo(map);
            tiles.on('tileerror', () => { mapStatus.textContent = 'Some map tiles could not load. Search controls and listing links remain available.'; });
            markers.forEach((item) => {
                const popup = document.createElement('div');
                const link = document.createElement('a');
                link.textContent = item.title;
                link.href = item.url;
                popup.appendChild(link);
                L.circleMarker([item.latitude, item.longitude], { radius: 6, color: '#226046', fillOpacity: 0.8 }).addTo(map).bindPopup(popup);
            });
            const choosePin = (lat, lng, changeSort = true) => {
                lng = ((lng + 180) % 360 + 360) % 360 - 180;
                if (!selectPoint(lat, lng)) return;
                if (changeSort) form.elements.sort.value = 'nearest';
                if (searchPin) searchPin.setLatLng([lat, lng]);
                else {
                    searchPin = L.marker([lat, lng], { draggable: true }).addTo(map).bindTooltip('Your search point');
                    searchPin.on('dragend', () => { const point = searchPin.getLatLng(); choosePin(point.lat, point.lng); });
                }
                const radius = Math.max(1, Math.min({{ (float) config('lamu.find_land_max_radius_km') }}, Number(form.elements.radius.value) || 25));
                if (searchCircle) searchCircle.setLatLng([lat, lng]).setRadius(radius * 1000);
                else searchCircle = L.circle([lat, lng], { radius: radius * 1000, weight: 1, fillOpacity: 0.04 }).addTo(map);
                mapStatus.textContent = 'Search pin selected. Nearby mode will search within your radius. Press Find land to see matching properties.';
            };
            if (centre) choosePin(centre.latitude, centre.longitude, false);
            form.elements.radius.addEventListener('input', () => {
                if (searchPin) { const point = searchPin.getLatLng(); choosePin(point.lat, point.lng, false); }
            });
            map.on('click', (event) => choosePin(event.latlng.lat, event.latlng.lng));
            if (!centre) mapStatus.textContent = 'Click or tap the map to place your search pin. Green markers show current listing results.';
        } catch (_) {
            mapStatus.textContent = 'The map could not load. Select a county/locality and choose Within county / locality above. Search results are still available.';
        }
    };
    initializeMap();
})();
</script>
