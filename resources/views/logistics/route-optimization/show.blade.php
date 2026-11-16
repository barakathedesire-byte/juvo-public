@extends('logistics.layouts.app')

@section('title', 'Route Plan')
@section('heading', 'Route plan')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Route Optimization</span><span aria-hidden="true"> / </span><span>{{ $plan->id }}</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Routing</span><h2>Route plan #{{ $plan->id }}</h2><p>Rider: {{ $plan->rider?->full_name ?? '—' }} · Method: {{ $plan->optimization_method }} · Provider: {{ $plan->provider ?: 'not available' }}</p></div>
    <div class="page-actions"><a href="{{ route('logistics.route-optimization.index') }}" class="btn btn-outline-secondary btn-sm">Back</a></div>
</div>

@if($plan->notes)
    <div class="alert alert-warning">{{ $plan->notes }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Planned distance</div><div class="stat-value">{{ $plan->planned_distance_km !== null ? number_format((float)$plan->planned_distance_km,2).' km' : '—' }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Planned duration</div><div class="stat-value">{{ $plan->planned_duration_minutes !== null ? $plan->planned_duration_minutes.' min' : '—' }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Actual distance</div><div class="stat-value">{{ $plan->actual_distance_km !== null ? number_format((float)$plan->actual_distance_km,2).' km' : 'Not recorded' }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Stops</div><div class="stat-value">{{ $plan->stops->count() }}</div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Seq</th><th>Type</th><th>Order</th><th>Location</th><th>Coordinates</th><th>Status</th><th>Distance from previous</th><th>Duration from previous</th></tr></thead>
            <tbody>
                @forelse($plan->stops as $stop)
                    <tr>
                        <td>{{ $stop->sequence }}</td>
                        <td>{{ $stop->stop_type }}</td>
                        <td>{{ $stop->order?->logistics_tracking_id ?? ($stop->order_id ? '#'.$stop->order_id : '—') }}</td>
                        <td>{{ $stop->location?->name ?? '—' }}</td>
                        <td class="small">{{ $stop->latitude !== null ? $stop->latitude.','.$stop->longitude : 'Unavailable' }}</td>
                        <td>{{ $stop->status }}</td>
                        <td>{{ $stop->distance_from_previous_km !== null ? number_format((float)$stop->distance_from_previous_km,2).' km' : '—' }}</td>
                        <td>{{ $stop->duration_from_previous_minutes !== null ? $stop->duration_from_previous_minutes.' min' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No stops.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
