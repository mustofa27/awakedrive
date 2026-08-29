@php
    $isSuperAdmin = auth()->user()?->hasRole('super_admin') ?? false;
@endphp

<div>
    <x-filament-panels::page>
        <div>
            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fleet control center</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-950">Live vehicle monitoring</h2>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        {{ count($mapDevices) }} tracked
                    </span>
                </div>

                <div class="fleet-cards flex snap-x overflow-x-auto pb-2" id="fleet-cards">
                    @forelse ($mapDevices as $device)
                        @php
                            $isAlert = in_array($device['driver_status'], ['drowsy', 'microsleep'], true);
                            $isOnline = $device['status'] === 'active';
                        @endphp
                        <button type="button" class="fleet-card {{ $isAlert ? 'fleet-card--alert' : 'fleet-card--safe' }} {{ $loop->first ? 'is-selected' : '' }} snap-start rounded-lg border bg-white p-3 text-left shadow-sm transition" data-device-id="{{ $device['id'] }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-950">{{ $device['name'] }}</p>
                                    <p class="mt-0.5 truncate text-xs text-gray-500">{{ $device['vehicle_plate'] ?? $device['device_uid'] }}</p>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $isOnline ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $isOnline ? 'Online' : 'Offline' }}</span>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">Driver: <span class="font-medium text-gray-700">{{ $device['driver_name'] ?: 'Unassigned' }}</span></p>
                            <div class="mt-2 flex items-center justify-between rounded-md px-2.5 py-2 text-xs font-semibold {{ $isAlert ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' : 'bg-emerald-50 text-emerald-700' }}">
                                <span>{{ $device['driver_status_label'] }}</span>
                                <span>{{ $isAlert ? 'Attention' : 'Normal' }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500">No device telemetry is available yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="dashboard-map-section overflow-hidden rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
                @if (count($mapDevices) === 0)
                    <div class="flex h-96 items-center justify-center text-sm text-gray-500">The map will appear when a device sends its first location.</div>
                @else
                    <div id="fleet-map" class="fleet-map w-full rounded-md"></div>
                @endif
            </section>

            <section class="dashboard-summary-grid grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:gap-5 {{ $isSuperAdmin ? 'xl:grid-cols-6' : 'xl:grid-cols-4' }}">
                @if ($isSuperAdmin)
                    <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm"><p class="text-xs text-gray-500">Companies</p><p class="mt-1 text-xl font-semibold text-gray-950">{{ $stats['total_companies'] ?? 0 }}</p></div>
                @endif
                <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm"><p class="text-xs text-gray-500">Devices</p><p class="mt-1 text-xl font-semibold text-gray-950">{{ $stats['total_devices'] ?? 0 }}</p></div>
                @if ($isSuperAdmin)
                    <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm"><p class="text-xs text-gray-500">Users</p><p class="mt-1 text-xl font-semibold text-gray-950">{{ $stats['total_users'] ?? 0 }}</p></div>
                @endif
                <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2.5"><p class="text-xs text-emerald-700">Active now</p><p class="mt-1 text-xl font-semibold text-emerald-950">{{ $stats['active_now'] ?? 0 }}</p></div>
                <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2.5"><p class="text-xs text-rose-700">Safety alerts</p><p class="mt-1 text-xl font-semibold text-rose-950">{{ $stats['alerts_today'] ?? 0 }}</p></div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5"><p class="text-xs text-gray-600">Offline</p><p class="mt-1 text-xl font-semibold text-gray-950">{{ $stats['offline_devices'] ?? 0 }}</p></div>
            </section>

            <section class="dashboard-alerts-section overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3"><p class="text-sm font-semibold text-gray-950">Recent safety alerts</p><p class="mt-0.5 text-xs text-gray-500">Latest driver-monitoring events</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-2.5">Device</th><th class="px-4 py-2.5">Driver state</th><th class="px-4 py-2.5">Triggered</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse (\App\Models\DeviceAlert::with('device')->when(auth()->user()?->company_id, fn ($query) => $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('company_id', auth()->user()->company_id)))->latest('triggered_at')->limit(8)->get() as $alert)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $alert->device?->device_uid ?? 'Unknown device' }}</td>
                                    <td class="px-4 py-3"><span @class(['rounded-full px-2 py-1 text-xs font-semibold', 'bg-rose-50 text-rose-700' => $alert->driver_status->value === 'microsleep', 'bg-amber-50 text-amber-700' => $alert->driver_status->value === 'drowsy'])>{{ $alert->driver_status->label() }}</span></td>
                                    <td class="px-4 py-3 text-gray-500">{{ $alert->triggered_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">No safety alerts have been recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <style>
            .fleet-map { height: clamp(20rem, 48vh, 30rem); }
            .dashboard-map-section { margin-top: 1.25rem; }
            .dashboard-summary-grid { margin-top: 2rem; }
            .dashboard-alerts-section { margin-top: 2rem; }
            .fleet-cards { gap: clamp(0.75rem, 1.5vw, 1.5rem); }
            .fleet-card { border-color: #e5e7eb; border-top-width: 3px; flex: 0 0 clamp(16rem, 22vw, 20rem); }
            .fleet-card--safe { border-top-color: #10b981; }
            .fleet-card--alert { border-top-color: #f43f5e; }
            .fleet-card:hover, .fleet-card.is-selected { border-color: #2563eb; box-shadow: 0 10px 24px rgb(37 99 235 / 14%); transform: translateY(-2px); }
            .fleet-card.is-selected { background: #f8fbff; }
            .fleet-truck-marker { background: transparent; border: 0; }
            .fleet-truck-marker span { align-items: center; background: var(--truck-color); border: 2px solid #fff; border-radius: 50%; box-shadow: 0 3px 8px rgb(15 23 42 / 28%); color: #fff; display: flex; font-size: 18px; height: 34px; justify-content: center; position: relative; width: 34px; }
            .fleet-truck-marker span::after { border-left: 7px solid transparent; border-right: 7px solid transparent; border-top: 9px solid var(--truck-color); bottom: -8px; content: ''; left: 8px; position: absolute; }
            html.dark .fleet-card,
            html.dark .dashboard-map-section,
            html.dark .dashboard-summary-grid > div,
            html.dark .dashboard-alerts-section { background: #18292b; border-color: #365255; }
            html.dark .fleet-card.is-selected { background: #1d3538; border-color: #f2b84b; }
            html.dark .fleet-card .text-gray-950,
            html.dark .fleet-card .text-gray-700,
            html.dark .dashboard-summary-grid .text-gray-950,
            html.dark .dashboard-alerts-section .text-gray-950,
            html.dark .dashboard-alerts-section .text-gray-900 { color: #edf7f4; }
            html.dark .fleet-card .text-gray-500,
            html.dark .dashboard-summary-grid .text-gray-500,
            html.dark .dashboard-summary-grid .text-gray-600,
            html.dark .dashboard-alerts-section .text-gray-500 { color: #a9c3be; }
            html.dark .dashboard-summary-grid > div.bg-emerald-50 { background: #143b35; border-color: #246052; }
            html.dark .dashboard-summary-grid > div.bg-rose-50 { background: #45242d; border-color: #793849; }
            html.dark .dashboard-summary-grid > div.bg-gray-50 { background: #223538; border-color: #365255; }
            html.dark .dashboard-alerts-section .border-b,
            html.dark .dashboard-alerts-section .divide-y > :not([hidden]) ~ :not([hidden]) { border-color: #365255; }
            html.dark .dashboard-alerts-section .bg-gray-50,
            html.dark .dashboard-alerts-section .bg-white { background: #203537; }
        </style>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (() => {
                const mapElement = document.getElementById('fleet-map');
                const fleetMapData = @json($mapDevices);
                if (!mapElement || mapElement.dataset.initialized || !window.L) return;

                mapElement.dataset.initialized = 'true';
                const map = L.map(mapElement).setView([0, 0], 2);
                const markers = {};
                const statusColors = { active: '#16a34a', inactive: '#64748b', maintenance: '#d97706', offline: '#64748b' };
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);

                fleetMapData.forEach((device) => {
                    const color = statusColors[device.status] || '#334155';
                    const marker = L.marker([device.latitude, device.longitude], {
                        icon: L.divIcon({
                            className: 'fleet-truck-marker',
                            html: `<span style="--truck-color: ${color}">&#128666;</span>`,
                            iconSize: [34, 43],
                            iconAnchor: [17, 43],
                            popupAnchor: [0, -40],
                        }),
                    }).addTo(map);
                    marker.bindPopup(`<strong>${device.name}</strong><br>${device.device_uid}<br>Driver: ${device.driver_name || 'Unassigned'}<br>Status: ${device.driver_status_label}`);
                    markers[device.id] = marker;
                });

                requestAnimationFrame(() => {
                    map.invalidateSize();
                    map.fitBounds(L.latLngBounds(fleetMapData.map((device) => [device.latitude, device.longitude])).pad(0.25));
                });
                document.querySelectorAll('.fleet-card').forEach((card) => card.addEventListener('click', () => {
                    document.querySelectorAll('.fleet-card').forEach((item) => item.classList.remove('is-selected'));
                    card.classList.add('is-selected');
                    const marker = markers[card.dataset.deviceId];
                    if (marker) { map.flyTo(marker.getLatLng(), 15, { duration: 0.8 }); marker.openPopup(); }
                }));
            })();
        </script>
    </x-filament-panels::page>
</div>
