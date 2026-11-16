@extends('logistics.layouts.app')

@section('title', 'Zones, Routes & Hubs')
@section('heading', 'Zones, Routes & Hubs')
@section('breadcrumb')
    <span>Logistics</span>
    <span aria-hidden="true">/</span>
    <span>Zones, Routes &amp; Hubs</span>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('zoneMap').setView([-6.7924, 39.2083], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
    const zones = @json($zones->map(fn($z)=>['name'=>$z->name,'polygon'=>$z->polygon]));
    zones.forEach(function (zone) {
        if (Array.isArray(zone.polygon) && zone.polygon.length) {
            const label = document.createElement('div');
            label.textContent = zone.name;
            L.polygon(zone.polygon, {color:'#f68b1e', weight:2, fillOpacity:0.12}).addTo(map).bindPopup(label);
        }
    });
});
</script>
@endpush

@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Delivery zones</h6>
                <button class="btn btn-sm btn-juvo" data-bs-toggle="modal" data-bs-target="#zoneModal">New zone</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Name</th><th>Code</th><th>Locations</th><th>Riders</th><th>Hub</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse($zones as $zone)
                                <tr>
                                    <td>{{ $zone->name }}</td>
                                    <td>{{ $zone->code }}</td>
                                    <td>{{ $zone->locations->count() }}</td>
                                    <td>{{ $zone->riders->count() }}</td>
                                    <td>{{ $zone->primaryHub?->name ?? '—' }}</td>
                                    <td>{{ $zone->is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#zoneEdit{{ $zone->id }}">Edit</button>
                                        <form method="POST" action="{{ route('logistics.zones.destroy', $zone) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state">No delivery zones yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Zone map</h6></div>
            <div class="card-body">
                <div id="zoneMap" style="height:360px;border-radius:12px;background:#eef2f7;"></div>
                <small class="text-muted">Zone polygons are displayed when valid GeoJSON coordinates are stored.</small>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="zoneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('logistics.zones.store') }}" class="modal-content">
            @csrf
            @include('logistics.zones._form', ['zone' => null, 'locations' => $locations, 'riders' => $riders, 'hubs' => $hubs])
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-juvo">Create</button></div>
        </form>
    </div>
</div>

@foreach($zones as $zone)
    <div class="modal fade" id="zoneEdit{{ $zone->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('logistics.zones.update', $zone) }}" class="modal-content">
                @csrf @method('PUT')
                @include('logistics.zones._form', ['zone' => $zone, 'locations' => $locations, 'riders' => $riders, 'hubs' => $hubs])
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-juvo">Save</button></div>
            </form>
        </div>
    </div>
@endforeach

<div class="page-header">
    <div>
        <span class="page-eyebrow">Network</span>
        <h2>Shared geography and logistics coverage</h2>
        <p>Regions and locations are managed in Juvo Admin. Logistics adds coverage, hub and rider settings without duplicating geography.</p>
    </div>
</div>

@foreach($regions as $region)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0">{{ $region->name }}</h6>
                <span class="text-muted small">Region ID: {{ $region->id }} · {{ $region->country ?: 'Tanzania' }} · {{ $region->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <span class="badge bg-secondary">{{ $region->locations->count() }} locations</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>ID</th>
                            <th>Marketplace fees</th>
                            <th>Coverage</th>
                            <th>Delivery zone</th>
                            <th>Hub</th>
                            <th>Assigned rider</th>
                            <th>Override fee</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($region->locations as $location)
                            @php $settings = $location->logisticsSettings; @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $location->name }}</div>
                                    <div class="text-muted small">{{ $location->postal_code ?: 'No postal code' }}</div>
                                </td>
                                <td>{{ $location->id }}</td>
                                <td class="small">
                                    Door: {{ number_format((float) $location->shipping_fee, 2) }}<br>
                                    Pickup: {{ $location->pickup_fee !== null ? number_format((float) $location->pickup_fee, 2) : '—' }}
                                </td>
                                <td>
                                    @if($location->is_active && ($settings?->is_covered ?? true))
                                        <span class="badge-status status-delivered">Covered</span>
                                    @else
                                        <span class="badge-status status-cancelled">Unavailable</span>
                                    @endif
                                </td>
                                <td>{{ $settings?->delivery_zone ?: '—' }}</td>
                                <td>{{ $settings?->hub_name ?: '—' }}</td>
                                <td>{{ $settings?->assignedRider?->full_name ?: '—' }}</td>
                                <td>{{ $settings?->delivery_fee_override !== null ? number_format((float) $settings->delivery_fee_override, 2) : '—' }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#settings{{ $location->id }}">Configure</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="empty-state py-3">No locations in this region.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach($region->locations as $location)
        @php $settings = $location->logisticsSettings; @endphp
        <div class="modal fade" id="settings{{ $location->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('logistics.zones.locations.settings', $location) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Logistics settings: {{ $location->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_covered" value="1" id="covered{{ $location->id }}" @checked($settings?->is_covered ?? true)>
                            <label class="form-check-label" for="covered{{ $location->id }}">Covered for delivery</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delivery zone</label>
                            <input type="text" name="delivery_zone" value="{{ $settings?->delivery_zone }}" class="form-control" maxlength="120">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hub name</label>
                            <input type="text" name="hub_name" value="{{ $settings?->hub_name }}" class="form-control" maxlength="120">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned rider</label>
                            <select name="assigned_rider_id" class="form-select">
                                <option value="">Unassigned</option>
                                @foreach($riders as $rider)
                                    <option value="{{ $rider->id }}" @selected((int) ($settings?->assigned_rider_id) === (int) $rider->id)>{{ $rider->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delivery fee override</label>
                            <input type="number" step="0.01" min="0" name="delivery_fee_override" value="{{ $settings?->delivery_fee_override }}" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Service notes</label>
                            <textarea name="service_notes" class="form-control" rows="3" maxlength="1000">{{ $settings?->service_notes }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-juvo">Save settings</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endforeach

@if($regions->isEmpty())
    <div class="empty-state">
        <div class="icon"><i class="fas fa-map-marked-alt"></i></div>
        <strong>No regions configured</strong>
        <p>Create regions and locations in Juvo Admin; they will appear here automatically.</p>
    </div>
@endif
@endsection
