@extends('logistics.layouts.app')

@section('title', 'Route Optimization')
@section('heading', 'Route optimization')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Route Optimization</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Routing</span><h2>Multi-stop route optimization</h2><p>Generate route plans from real coordinates. Road distances use OSRM when available; otherwise a clearly labeled straight-line fallback is used.</p></div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.route-optimization.store') }}" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Rider</label>
                <select name="rider_id" class="form-select" required>
                    <option value="">Select rider</option>
                    @foreach($riders as $rider)
                        <option value="{{ $rider->id }}">{{ $rider->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Active orders</label>
                <select name="order_ids[]" class="form-select" multiple size="8" required>
                    @foreach($orders as $order)
                        <option value="{{ $order->id }}">{{ $order->logistics_tracking_id ?? '#'.$order->id }} — {{ $order->pickupLocation?->name ?? 'pickup unset' }} to {{ $order->shippingAddress?->city ?? 'destination unset' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12"><button class="btn btn-juvo">Generate route plan</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Rider</th><th>Status</th><th>Method</th><th>Planned distance</th><th>Planned duration</th><th>Stops</th><th>Created</th><th></th></tr></thead>
            <tbody>
                @forelse($plans as $plan)
                    <tr>
                        <td>{{ $plan->id }}</td>
                        <td>{{ $plan->rider?->full_name ?? '—' }}</td>
                        <td>{{ $plan->status }}</td>
                        <td>{{ $plan->optimization_method }}</td>
                        <td>{{ $plan->planned_distance_km !== null ? number_format((float)$plan->planned_distance_km,2).' km' : '—' }}</td>
                        <td>{{ $plan->planned_duration_minutes !== null ? $plan->planned_duration_minutes.' min' : '—' }}</td>
                        <td>{{ $plan->stops->count() }}</td>
                        <td>{{ optional($plan->created_at)->format('d M Y H:i') }}</td>
                        <td class="text-end"><a href="{{ route('logistics.route-optimization.show', $plan) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">No route plans yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $plans->links() }}</div>
</div>
@endsection
