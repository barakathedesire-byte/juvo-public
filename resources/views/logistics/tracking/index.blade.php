@extends('logistics.layouts.app')

@section('title', 'Live Tracking & Fleet')
@section('heading', 'Fleet monitor')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">Operations</div>
            <h2 class="page-title">Live Tracking &amp; Fleet</h2>
            <p class="page-subtitle">Real rider locations and active deliveries from reported GPS data. Locations are shown only when a rider has reported coordinates.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge-status status-in_transit">{{ $activeDeliveries->count() }} active deliveries</span>
            <span class="badge-status {{ $staleRiders->isEmpty() ? 'status-delivered' : 'status-failed' }}">{{ $staleRiders->count() }} location alerts</span>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="surface">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-0">Fleet map</h5>
                        <div class="text-muted small mt-1">Rider markers use the latest reported GPS position.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="search" id="fleetSearch" class="form-control form-control-sm" placeholder="Search rider or order" style="width: 220px;">
                        <button type="button" id="refreshFleet" class="btn btn-sm btn-outline-secondary"><i class="fas fa-rotate-right"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="fleetMap" class="fleet-map" aria-label="Fleet tracking map"></div>
                    <div class="small text-muted mt-2">
                        Delayed-location alerts are based on the rider's last reported update. Stationary movement cannot be determined because no location history is stored.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="surface mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Location alerts</h5>
                </div>
                <div class="card-body">
                    @forelse ($staleRiders as $rider)
                        <div class="queue-item">
                            <span class="queue-icon" style="background: #fff1f2; color: #b72f3b;"><i class="fas fa-clock"></i></span>
                            <span class="queue-body">
                                <strong>{{ $rider->full_name }}</strong>
                                <span>{{ $rider->active_deliveries_count }} active delivery · last update {{ $rider->last_online_at?->diffForHumans() ?? 'never' }}</span>
                            </span>
                        </div>
                    @empty
                        <div class="empty-state py-3">
                            <div class="icon"><i class="fas fa-shield-heart"></i></div>
                            <strong>No location alerts</strong>
                            <p>Active riders have recent location updates.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="surface">
                <div class="card-header">
                    <h5 class="mb-0">Rider details</h5>
                </div>
                <div class="card-body" id="riderDetails">
                    <p class="text-muted small mb-0">Select a rider marker to view assignment and location details.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="surface mt-3">
        <div class="card-header">
            <h5 class="mb-0">Active deliveries</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr><th>Order</th><th>Rider</th><th>Status</th><th>Pickup</th><th>Destination</th><th>Assigned</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($activeDeliveries as $order)
                            <tr>
                                <td><a href="{{ route('logistics.orders.show', $order) }}" class="link-juvo">#{{ $order->id }}</a></td>
                                <td>{{ $order->assignedRider?->full_name ?? 'Unassigned' }}</td>
                                <td><span class="badge-status status-{{ $order->logistics_status }}">{{ $order->logisticsStatusLabel() }}</span></td>
                                <td class="small">{{ $order->pickupLocation->name ?? '—' }}</td>
                                <td class="small">{{ $order->shippingAddress->city ?? $order->shippingAddress->address ?? '—' }}</td>
                                <td class="text-muted small">{{ $order->assigned_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center py-3">No active deliveries.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
        <script>
            const fleetMapElement = document.getElementById('fleetMap');
            const fleetDataUrl = @json(route('logistics.tracking.data'));
            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, function (character) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
                });
            }
            let fleetMap = null;
            let riderMarkers = [];
            let deliveryMarkers = [];

            function renderFleet(data) {
                if (!fleetMapElement || !window.L) return;

                if (!fleetMap) {
                    fleetMap = L.map(fleetMapElement).setView([0, 0], 2);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(fleetMap);
                }

                riderMarkers.forEach(marker => marker.remove());
                deliveryMarkers.forEach(marker => marker.remove());
                riderMarkers = [];
                deliveryMarkers = [];

                const term = document.getElementById('fleetSearch')?.value?.toLowerCase() || '';
                const riders = (data.riders || []).filter(rider => !term || rider.name.toLowerCase().includes(term));
                const deliveries = (data.deliveries || []).filter(delivery => !term || String(delivery.id).includes(term) || (delivery.rider || '').toLowerCase().includes(term));

                riders.forEach(rider => {
                    const marker = L.circleMarker([rider.lat, rider.lng], {
                        radius: 8,
                        color: rider.online ? '#118a5b' : '#687386',
                        fillColor: rider.online ? '#118a5b' : '#687386',
                        fillOpacity: 0.9,
                    }).addTo(fleetMap)
                        .bindPopup(`<strong>${escapeHtml(rider.name)}</strong><br>${rider.online ? 'Online' : 'Offline'} · ${escapeHtml(rider.active_deliveries)} active<br>Last update: ${escapeHtml(rider.last_update ? new Date(rider.last_update).toLocaleString() : 'Never')}`);
                    marker.on('click', () => {
                        const lastUpdate = rider.last_update ? new Date(rider.last_update).toLocaleString() : 'Never';
                        document.getElementById('riderDetails').innerHTML = `
                            <dl class="info-list mb-0">
                                <div class="info-row"><dt class="text-muted">Rider</dt><dd>${escapeHtml(rider.name)}</dd></div>
                                <div class="info-row"><dt class="text-muted">Vehicle</dt><dd>${escapeHtml(rider.vehicle || 'Not set')}</dd></div>
                                <div class="info-row"><dt class="text-muted">Status</dt><dd>${rider.online ? 'Online' : 'Offline'}</dd></div>
                                <div class="info-row"><dt class="text-muted">Active deliveries</dt><dd>${escapeHtml(rider.active_deliveries)}</dd></div>
                                <div class="info-row"><dt class="text-muted">Last update</dt><dd>${escapeHtml(lastUpdate)}</dd></div>
                                <div class="info-row"><dt class="text-muted">Location history</dt><dd>No stored location history is available.</dd></div>
                            </dl>
                        `;
                    });
                    riderMarkers.push(marker);
                });

                deliveries.forEach(delivery => {
                    if (delivery.driver_lat !== null && delivery.driver_lng !== null) {
                        const marker = L.marker([delivery.driver_lat, delivery.driver_lng])
                            .addTo(fleetMap)
                            .bindPopup(`<strong>Order #${escapeHtml(delivery.id)}</strong><br>${escapeHtml(delivery.status_label)}<br>${escapeHtml(delivery.rider || 'Unassigned')}`);
                        deliveryMarkers.push(marker);
                    }
                });

                if (riderMarkers.length || deliveryMarkers.length) {
                    fleetMap.fitBounds(L.featureGroup([...riderMarkers, ...deliveryMarkers]).getBounds().pad(0.2));
                }
            }

            async function loadFleet() {
                try {
                    const response = await fetch(fleetDataUrl, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) return;
                    renderFleet(await response.json());
                } catch (error) {
                    console.error(error);
                }
            }

            document.getElementById('fleetSearch')?.addEventListener('input', loadFleet);
            document.getElementById('refreshFleet')?.addEventListener('click', loadFleet);
            loadFleet();
            setInterval(loadFleet, 30000);
        </script>
    @endpush
@endsection
