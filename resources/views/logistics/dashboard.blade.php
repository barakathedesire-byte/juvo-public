@extends('logistics.layouts.app')

@section('title', 'Operations')
@section('heading', 'Operations overview')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">Dispatch control</div>
            <h2 class="page-title">Today at a glance</h2>
            <p class="page-subtitle">Live order flow, rider capacity, and cash position across the network.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group" role="group" aria-label="Reporting period">
                @foreach (['today' => 'Today', '7' => '7 days', '30' => '30 days'] as $value => $label)
                    <a href="{{ route('logistics.dashboard', ['period' => $value]) }}"
                       class="btn btn-sm {{ $period === $value ? 'btn-juvo' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
            <a href="{{ route('logistics.orders.index', ['assignment' => 'unassigned']) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-route me-1"></i>Dispatch queue
            </a>
            <a href="{{ route('logistics.dispatch.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-truck-ramp-box me-1"></i>Dispatch center
            </a>
            <a href="{{ route('logistics.tracking.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-location-crosshairs me-1"></i>Live tracking
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="surface metric-card is-orange">
                <div class="metric-top">
                    <span class="metric-label">Pending dispatch</span>
                    <i class="fas fa-clock"></i>
                </div>
                <div class="metric-value">{{ number_format($stats['pending_dispatch']) }}</div>
                <div class="metric-hint">{{ number_format($stats['unassigned']) }} without a rider</div>
                <a class="metric-link" href="{{ route('logistics.orders.index', ['status' => 'pending']) }}" aria-label="View pending dispatch orders"></a>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="surface metric-card is-blue">
                <div class="metric-top">
                    <span class="metric-label">On the road</span>
                    <i class="fas fa-truck-fast"></i>
                </div>
                <div class="metric-value">{{ number_format($stats['in_transit']) }}</div>
                <div class="metric-hint">Assigned, accepted, or moving</div>
                <a class="metric-link" href="{{ route('logistics.orders.index', ['status' => 'in_transit']) }}" aria-label="View orders on the road"></a>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="surface metric-card is-green">
                <div class="metric-top">
                    <span class="metric-label">Delivered</span>
                    <i class="fas fa-circle-check"></i>
                </div>
                <div class="metric-value">{{ number_format($stats['delivered_period']) }}</div>
                <div class="metric-hint">{{ $periodLabel }} · {{ $stats['delivery_rate'] !== null ? $stats['delivery_rate'].'% success' : 'No outcomes yet' }}</div>
                <a class="metric-link" href="{{ route('logistics.orders.index', ['status' => 'delivered']) }}" aria-label="View delivered orders"></a>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="surface metric-card is-danger">
                <div class="metric-top">
                    <span class="metric-label">Exceptions</span>
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div class="metric-value">{{ number_format($stats['failed_period']) }}</div>
                <div class="metric-hint">Failed or returned · {{ $periodLabel }}</div>
                <a class="metric-link" href="{{ route('logistics.orders.index', ['status' => 'failed']) }}" aria-label="View failed deliveries"></a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="hero-panel h-100">
                <div class="position-relative" style="z-index: 1;">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <div class="eyebrow text-warning mb-1">Cash position</div>
                            <h2>Keep COD moving to reconciliation</h2>
                            <p class="mb-0">Collected cash must be remitted before it is cleared. Amounts below come from live delivery records.</p>
                        </div>
                        <a href="{{ route('logistics.finance.index') }}" class="btn btn-juvo btn-sm">Open finance</a>
                    </div>
                    <div class="row g-2 mt-3">
                        <div class="col-6 col-md-3">
                            <div class="hero-stat">
                                <span>To collect</span>
                                <strong>{{ $cash['awaiting_count'] > 0 ? number_format($cash['awaiting'], 0) : '—' }}</strong>
                                <small class="text-white-50">{{ $cash['awaiting_count'] }} delivered orders</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat">
                                <span>Held by riders</span>
                                <strong>{{ $cash['outstanding'] > 0 ? number_format($cash['outstanding'], 0) : '—' }}</strong>
                                <small class="text-white-50">Awaiting remittance</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat">
                                <span>Avg delivery</span>
                                <strong>{{ $avgMinutes !== null ? $avgMinutes.' min' : '—' }}</strong>
                                <small class="text-white-50">Pickup to drop · 30 days</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat">
                                <span>Riders online</span>
                                <strong>{{ number_format($stats['active_riders']) }}</strong>
                                <small class="text-white-50">{{ $stats['pending_kyc'] }} KYC in review</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat">
                                <span>Overdue</span>
                                <strong>{{ number_format($overdueOrders) }}</strong>
                                <small class="text-white-50">Needs attention</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat">
                                <span>Revenue</span>
                                <strong>{{ number_format($revenuePeriod, 0) }}</strong>
                                <small class="text-white-50">{{ $periodLabel }}</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat">
                                <span>Rider capacity</span>
                                <strong>{{ number_format($availableRiders) }} / {{ number_format($busyRiders) }}</strong>
                                <small class="text-white-50">Available / busy</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="surface h-100">
                <div class="card-body">
                    <div class="section-title">
                        <h2>Rider capacity</h2>
                        <a href="{{ route('logistics.riders.index') }}" class="link-juvo small">All riders</a>
                    </div>
                    @forelse ($riderSnapshot as $rider)
                        <div class="mini-rider">
                            <span class="status-dot {{ $rider->is_online ? 'online' : '' }}"></span>
                            <div class="meta">
                                <strong>{{ $rider->full_name }}</strong>
                                <span>{{ Str::headline($rider->vehicle_type ?? 'rider') }} · {{ $rider->active_deliveries_count }} active</span>
                            </div>
                            <span class="badge-status {{ $rider->is_online ? 'status-online' : 'status-offline' }}">{{ $rider->is_online ? 'Online' : 'Offline' }}</span>
                        </div>
                    @empty
                        <div class="empty-state py-4">
                            <div class="icon"><i class="fas fa-motorcycle"></i></div>
                            <strong>No active riders</strong>
                            <p>Approved riders will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="surface h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Recent orders</h5>
                        <div class="text-muted small mt-1">Newest logistics records</div>
                    </div>
                    <a href="{{ route('logistics.orders.index') }}" class="link-juvo small">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Rider</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('logistics.orders.show', $order) }}" class="fw-semibold text-decoration-none" style="color: var(--juvo-ink);">
                                            {{ $order->logistics_tracking_id ?? '#'.$order->id }}
                                        </a>
                                        <div class="text-muted" style="font-size: .66rem;">{{ $order->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-placeholder" style="width: 28px; height: 28px; font-size: .65rem;">{{ Str::upper(Str::substr($order->customer->first_name ?? 'G', 0, 1)) }}</div>
                                            <span>{{ $order->customer->first_name ?? 'Guest' }} {{ $order->customer->last_name ?? '' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($order->assignedRider)
                                            {{ $order->assignedRider->full_name }}
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td><span class="badge-status status-{{ $order->logistics_status ?? 'pending' }}">{{ $order->logisticsStatusLabel() }}</span></td>
                                    <td class="text-end fw-semibold">{{ $order->currency ?? 'TZS' }} {{ number_format((float) $order->total_amount, 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <div class="icon"><i class="fas fa-box"></i></div>
                                            <strong>No orders yet</strong>
                                            <p>Orders created through the marketplace will appear here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="surface mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Dispatch queue</h5>
                    <span class="badge-status status-pending">{{ $dispatchQueue->count() }} waiting</span>
                </div>
                <div class="card-body">
                    @forelse ($dispatchQueue as $order)
                        <a href="{{ route('logistics.orders.show', $order) }}" class="queue-item text-decoration-none">
                            <span class="queue-icon"><i class="fas fa-location-dot"></i></span>
                            <span class="queue-body">
                                <strong>{{ $order->logistics_tracking_id ?? '#'.$order->id }}</strong>
                                <span>{{ $order->pickupLocation->name ?? 'Pickup unset' }} · {{ $order->created_at->diffForHumans() }}</span>
                            </span>
                            <span class="queue-meta"><span class="badge-status status-{{ $order->logistics_status }}">{{ $order->logisticsStatusLabel() }}</span></span>
                        </a>
                    @empty
                        <div class="empty-state py-3">
                            <div class="icon"><i class="fas fa-circle-check"></i></div>
                            <strong>Queue is clear</strong>
                            <p>No orders waiting for dispatch.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="surface">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Needs attention</h5>
                    <a href="{{ route('logistics.orders.index', ['status' => 'failed']) }}" class="link-juvo small">Review</a>
                </div>
                <div class="card-body">
                    @forelse ($exceptionOrders as $order)
                        <div class="queue-item">
                            <span class="queue-icon" style="background: #fff1f2; color: #b72f3b;"><i class="fas fa-triangle-exclamation"></i></span>
                            <span class="queue-body">
                                <strong>{{ $order->logistics_tracking_id ?? '#'.$order->id }}</strong>
                                <span>{{ Str::limit($order->failure_note ?: ($order->failed_reason ? Str::headline($order->failed_reason) : 'Delivery needs review'), 44) }}</span>
                            </span>
                            <a href="{{ route('logistics.orders.show', $order) }}" class="btn btn-outline-secondary btn-sm">Open</a>
                        </div>
                    @empty
                        <div class="empty-state py-3">
                            <div class="icon"><i class="fas fa-shield-heart"></i></div>
                            <strong>No exceptions</strong>
                            <p>Failed and returned deliveries will surface here.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="surface h-100">
                <div class="card-header">
                    <h5 class="mb-0">Order intake</h5>
                    <div class="text-muted small mt-1">Last 7 days</div>
                </div>
                <div class="card-body">
                    <div class="chart-shell"><canvas id="deliveryChart" aria-label="Orders created over the last 7 days" role="img"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="surface h-100">
                <div class="card-header">
                    <h5 class="mb-0">Delivery activity</h5>
                    <div class="text-muted small mt-1">Latest lifecycle events</div>
                </div>
                <div class="card-body">
                    <div class="activity-feed">
                        @forelse ($activity as $event)
                            <div class="activity-item">
                                <span class="activity-dot"><i class="fas fa-bolt"></i></span>
                                <div>
                                    <p>
                                        <strong>{{ Str::headline($event->type) }}</strong>
                                        @if ($event->order)
                                            on <a href="{{ route('logistics.orders.show', $event->order) }}" class="link-juvo">#{{ $event->order_id }}</a>
                                        @else
                                            on order #{{ $event->order_id }}
                                        @endif
                                        @if ($event->rider) · {{ $event->rider->full_name }} @endif
                                    </p>
                                    <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->diffForHumans() }}</time>
                                </div>
                            </div>
                        @empty
                            <div class="empty-state py-3">
                                <div class="icon"><i class="fas fa-stream"></i></div>
                                <strong>No events yet</strong>
                                <p>Delivery transitions will be logged here.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const chartCanvas = document.getElementById('deliveryChart');
        if (chartCanvas) {
            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: @json($deliveryStats->pluck('date')->map(fn ($date) => $date->format('D'))),
                    datasets: [{
                        data: @json($deliveryStats->pluck('count')),
                        borderColor: '#f68b1e',
                        backgroundColor: 'rgba(246, 139, 30, .12)',
                        fill: true,
                        tension: .38,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#f68b1e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { displayColors: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#8c97a8', font: { size: 10 } }, grid: { color: '#eef1f6' }, border: { display: false } },
                        x: { ticks: { color: '#8c97a8', font: { size: 10 } }, grid: { display: false }, border: { display: false } }
                    }
                }
            });
        }
    </script>
@endpush
