<div class="space-y-3" wire:ignore>
    <div class="flex flex-wrap gap-2" role="group" aria-label="Map selection mode">
        <button type="button" class="trip-map-mode is-active" data-mode="start">Set start</button>
        <button type="button" class="trip-map-mode" data-mode="finish">Set destination</button>
    </div>
    <div id="trip-route-map" class="trip-route-map"></div>
    <p class="text-xs text-gray-500 dark:text-gray-400">Select a mode, then click the map. Start is green; destination is amber.</p>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    (() => {
        const mapElement = document.getElementById('trip-route-map');
        if (!mapElement || mapElement.dataset.initialized || !window.L) return;

        mapElement.dataset.initialized = 'true';
        const wire = window.Livewire.find(mapElement.closest('[wire\\:id]').getAttribute('wire:id'));
        const initialStart = [@json($get('start_latitude')), @json($get('start_longitude'))].map(Number);
        const initialFinish = [@json($get('finish_latitude')), @json($get('finish_longitude'))].map(Number);
        const defaultCenter = [-6.2, 106.816666];
        const map = L.map(mapElement).setView(initialStart.every(Number.isFinite) ? initialStart : defaultCenter, initialStart.every(Number.isFinite) ? 13 : 11);
        const markers = {};
        let routeLine;
        let mode = 'start';

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);

        const updateRoute = () => {
            const start = [wire.get('data.start_latitude'), wire.get('data.start_longitude')].map(Number);
            const finish = [wire.get('data.finish_latitude'), wire.get('data.finish_longitude')].map(Number);
            if (!start.every(Number.isFinite) || !finish.every(Number.isFinite)) return;
            if (routeLine) routeLine.remove();
            routeLine = L.polyline([start, finish], { color: '#2563eb', dashArray: '6 8', weight: 3 }).addTo(map);
        };

        const setPoint = (pointMode, latitude, longitude) => {
            const label = pointMode === 'start' ? 'Start' : 'Destination';
            const color = pointMode === 'start' ? '#16a34a' : '#d97706';
            wire.set(`data.${pointMode}_latitude`, latitude);
            wire.set(`data.${pointMode}_longitude`, longitude);
            if (markers[pointMode]) markers[pointMode].remove();
            markers[pointMode] = L.circleMarker([latitude, longitude], { radius: 9, color, fillColor: color, fillOpacity: 0.9, weight: 2 }).addTo(map).bindTooltip(label, { permanent: true, direction: 'top' });
            updateRoute();
        };

        if (initialStart.every(Number.isFinite)) setPoint('start', initialStart[0], initialStart[1]);
        if (initialFinish.every(Number.isFinite)) setPoint('finish', initialFinish[0], initialFinish[1]);
        if (initialStart.every(Number.isFinite) && initialFinish.every(Number.isFinite)) map.fitBounds(L.latLngBounds([initialStart, initialFinish]).pad(0.25));

        map.on('click', (event) => setPoint(mode, event.latlng.lat, event.latlng.lng));
        document.querySelectorAll('.trip-map-mode').forEach((button) => button.addEventListener('click', () => {
            mode = button.dataset.mode;
            document.querySelectorAll('.trip-map-mode').forEach((item) => item.classList.toggle('is-active', item === button));
        }));
        requestAnimationFrame(() => map.invalidateSize());
    })();
</script>

<style>
    .trip-route-map { height: 26rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
    .trip-map-mode { border: 1px solid #d1d5db; border-radius: 0.375rem; color: #374151; font-size: 0.8125rem; font-weight: 600; padding: 0.5rem 0.75rem; }
    .trip-map-mode.is-active { background: #0f766e; border-color: #0f766e; color: #fff; }
    html.dark .trip-route-map { border-color: #365255; }
    html.dark .trip-map-mode { border-color: #365255; color: #c7d8d4; }
    html.dark .trip-map-mode.is-active { background: #14b8a6; border-color: #14b8a6; color: #102b2e; }
</style>