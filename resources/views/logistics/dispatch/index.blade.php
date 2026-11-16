@extends('logistics.layouts.app')

@section('title', 'Dispatch & Allocation')
@section('heading', 'Intelligent dispatch control center')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Dispatch</span>
@endsection

@section('content')
<div class="page-header">
    <div>
        <span class="page-eyebrow">Operations</span>
        <h2>Intelligent dispatch</h2>
        <p>Recommendations are calculated from availability, workload, service area, performance, priority, and available coordinates. Missing data is identified rather than treated as certain.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('logistics.dispatch.config') }}" class="btn btn-outline-secondary btn-sm">Dispatch rules</a>
        <span class="badge-status status-pending">{{ $unassignedOrders->count() }} unassigned</span>
        <span class="badge-status status-in_transit">{{ $riders->where('is_online', true)->count() }} riders online</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="surface h-100">
            <div class="card-header"><h5 class="mb-0">Unassigned queue</h5></div>
            <div class="card-body dispatch-queue">
                @forelse($unassignedOrders as $order)
                    @php($candidates = $recommendationMap[$order->id] ?? [])
                    <div class="dispatch-order">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <a href="{{ route('logistics.orders.show', $order) }}" class="fw-semibold text-decoration-none" style="color: var(--juvo-ink);">
                                {{ $order->logistics_tracking_id ?? '#'.$order->id }}
                            </a>
                            <span class="badge-status status-{{ $order->logistics_priority === 'urgent' ? 'failed' : ($order->logistics_priority === 'high' ? 'in_transit' : 'pending') }}">
                                {{ \Illuminate\Support\Str::headline($order->logistics_priority ?: 'normal') }}
                            </span>
                        </div>
                        <div class="small text-muted mb-2">
                            {{ $order->pickupLocation->name ?? 'Pickup unset' }}
                            <i class="fas fa-arrow-right mx-1"></i>
                            {{ $order->shippingAddress->city ?? $order->shippingAddress->address ?? 'Destination unset' }}
                        </div>
                        @if(!empty($candidates))
                            @php($top = $candidates[0])
                            <div class="small mb-2">
                                <strong>Recommended:</strong> {{ $top['rider']->full_name }} (score {{ $top['score'] }})
                                @if($top['distance_km'] !== null)
                                    · {{ number_format($top['distance_km'], 2) }} km
                                @else
                                    · distance unavailable
                                @endif
                            </div>
                            <details class="small text-muted mb-2">
                                <summary>Why this rider?</summary>
                                <ul class="mb-0">
                                    @foreach($top['factors'] as $factor)
                                        <li>{{ $factor['factor'] }}: {{ $factor['detail'] }} (contribution {{ $factor['contribution'] }})</li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <div class="small text-warning mb-2">No eligible riders found.</div>
                        @endif
                        <form method="POST" action="{{ route('logistics.orders.assign', $order) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-12">
                                <select name="rider_id" class="form-select form-select-sm" required>
                                    <option value="">Choose rider…</option>
                                    @foreach($riders as $rider)
                                        <option value="{{ $rider->id }}">
                                            {{ $rider->full_name }} — {{ $rider->is_online ? 'online' : 'offline' }} · {{ $rider->active_deliveries_count }} active
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 d-grid">
                                <button class="btn btn-sm btn-juvo">Assign selected</button>
                            </div>
                            <div class="col-6 d-grid">
                                <a href="{{ route('logistics.dispatch.recommend', $order) }}" class="btn btn-sm btn-outline-secondary" target="_blank">JSON recommendations</a>
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="empty-state py-4">
                        <div class="icon"><i class="fas fa-circle-check"></i></div>
                        <strong>Queue is clear</strong>
                        <p>No deliveries are waiting for assignment.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="surface h-100">
            <div class="card-header"><h5 class="mb-0">Candidate scores</h5></div>
            <div class="card-body">
                @foreach($unassignedOrders as $order)
                    @php($candidates = $recommendationMap[$order->id] ?? [])
                    @if(!empty($candidates))
                        <div class="mb-3">
                            <strong>{{ $order->logistics_tracking_id ?? '#'.$order->id }}</strong>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Rider</th><th>Score</th><th>Distance</th><th>Workload</th></tr></thead>
                                    <tbody>
                                        @foreach(array_slice($candidates, 0, 5) as $candidate)
                                            <tr>
                                                <td>{{ $candidate['rider']->full_name }}</td>
                                                <td>{{ $candidate['score'] }}</td>
                                                <td>{{ $candidate['distance_km'] !== null ? number_format($candidate['distance_km'], 2).' km' : 'Unavailable' }}</td>
                                                <td>{{ $candidate['rider']->active_deliveries_count }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="surface h-100">
            <div class="card-header"><h5 class="mb-0">Active assignments</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Order</th><th>Rider</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($activeAssignments as $order)
                                <tr>
                                    <td><a href="{{ route('logistics.orders.show', $order) }}" class="link-juvo">#{{ $order->id }}</a></td>
                                    <td>{{ $order->assignedRider?->full_name ?? 'Unassigned' }}</td>
                                    <td><span class="badge-status status-{{ $order->logistics_status }}">{{ $order->logisticsStatusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="empty-state">No active deliveries.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
