@extends('logistics.layouts.app')

@section('title', 'Routes')
@section('heading', 'Routes Management')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Routes</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Network</span><h2>Routes</h2><p>Store pickup and destination route records. Distances use configured coordinates only; no fabricated routing data.</p></div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.routes.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-3"><label class="form-label">Origin location</label><select name="origin_location_id" class="form-select" required><option value="">Select</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }} ({{ $location->region?->name }})</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Destination location</label><select name="destination_location_id" class="form-select" required><option value="">Select</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }} ({{ $location->region?->name }})</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Distance km</label><input type="number" step="0.01" name="distance_km" class="form-control" placeholder="Auto"></div>
            <div class="col-md-2"><label class="form-label">Duration min</label><input type="number" name="duration_minutes" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-juvo w-100">Save route</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Origin</th><th>Destination</th><th>Distance</th><th>Duration</th><th>Status</th><th>Notes</th><th></th></tr></thead>
            <tbody>
                @forelse($routes as $route)
                    <tr>
                        <td>{{ $route->origin?->name }} ({{ $route->origin?->region?->name }})</td>
                        <td>{{ $route->destination?->name }} ({{ $route->destination?->region?->name }})</td>
                        <td>{{ $route->distance_km !== null ? number_format((float)$route->distance_km,2).' km' : 'Unavailable' }}</td>
                        <td>{{ $route->duration_minutes !== null ? $route->duration_minutes.' min' : 'Unavailable' }}</td>
                        <td>{{ $route->status }}</td>
                        <td class="small">{{ $route->notes ?: '—' }}</td>
                        <td class="text-end"><form method="POST" action="{{ route('logistics.routes.destroy', $route) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">No routes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $routes->links() }}</div>
</div>
@endsection
