@extends('logistics.layouts.app')

@section('title', 'Orders')
@section('heading', 'Delivery orders')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">Dispatch desk</div>
            <h2 class="page-title">Orders</h2>
            <p class="page-subtitle">Track every consignment from intake to proof of delivery.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('logistics.orders.index', ['assignment' => 'unassigned']) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-user-slash me-1"></i>{{ $summary['unassigned'] }} unassigned
            </a>
            <a href="{{ route('logistics.orders.index', ['status' => 'failed']) }}" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-triangle-exclamation me-1"></i>{{ $summary['exceptions'] }} exceptions
            </a>
            <a href="{{ route('logistics.orders.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-file-csv me-1"></i>Export
            </a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach ([
            ['label' => 'All orders', 'value' => $summary['all'], 'filter' => [], 'color' => 'orange'],
            ['label' => 'Waiting', 'value' => $summary['pending'], 'filter' => ['status' => 'pending'], 'color' => 'warning'],
            ['label' => 'On the road', 'value' => $summary['active'], 'filter' => ['status' => 'in_transit'], 'color' => 'blue'],
            ['label' => 'Delivered', 'value' => $summary['delivered'], 'filter' => ['status' => 'delivered'], 'color' => 'green'],
            ['label' => 'Exceptions', 'value' => $summary['exceptions'], 'filter' => ['status' => 'failed'], 'color' => 'red'],
        ] as $tile)
            <div class="col-6 col-lg">
                <a href="{{ route('logistics.orders.index', $tile['filter']) }}" class="surface metric-card is-{{ $tile['color'] }} text-decoration-none">
                    <span class="metric-label">{{ $tile['label'] }}</span>
                    <span class="metric-value">{{ number_format($tile['value']) }}</span>
                </a>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('logistics.orders.index') }}" class="filter-bar mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3">
                <label class="form-label">Search</label>
                <div class="position-relative">
                    <i class="fas fa-magnifying-glass position-absolute" style="left: .8rem; top: 50%; transform: translateY(-50%); color: #8c97a8; font-size: .72rem;"></i>
                    <input type="search" name="q" value="{{ $search }}" class="form-control ps-4" placeholder="Tracking ID or order number">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ Str::headline($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Assignment</label>
                <select name="assignment" class="form-select">
                    <option value="">Any rider</option>
                    <option value="unassigned" @selected($assignment === 'unassigned')>Unassigned</option>
                    <option value="assigned" @selected($assignment === 'assigned')>Assigned</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">Any priority</option>
                    @foreach (['low', 'normal', 'high', 'urgent'] as $option)
                        <option value="{{ $option }}" @selected($priority === $option)>{{ Str::headline($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Rider</label>
                <select name="rider" class="form-select">
                    <option value="">All riders</option>
                    @foreach ($riders as $rider)
                        <option value="{{ $rider->id }}" @selected((string) $riderId === (string) $rider->id)>{{ $rider->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-1">
                <label class="form-label">Sort</label>
                <select name="sort" class="form-select">
                    <option value="">Newest</option>
                    <option value="oldest" @selected($sort === 'oldest')>Oldest</option>
                    <option value="amount" @selected($sort === 'amount')>Amount</option>
                    <option value="delivered" @selected($sort === 'delivered')>Delivered</option>
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-juvo flex-fill">Apply</button>
                <a href="{{ route('logistics.orders.index') }}" class="btn btn-outline-secondary" aria-label="Reset filters"><i class="fas fa-rotate-left"></i></a>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-6 col-lg-2">
                <label class="form-label">Pickup point</label>
                <select name="location" class="form-select">
                    <option value="">All locations</option>
                    @foreach ($locations as $pickup)
                        <option value="{{ $pickup->id }}" @selected((string) $location === (string) $pickup->id)>{{ $pickup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
            </div>
            <div class="col-lg-6 d-flex align-items-end">
                <div class="small text-muted">
                    <i class="fas fa-circle-info me-1"></i>
                    {{ number_format($orders->total()) }} orders match. Delivery state is controlled by the dispatch workflow.
                </div>
            </div>
        </div>
    </form>

    <div class="surface">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Route</th>
                        <th>Rider</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th class="text-end">Value</th>
                        <th>Placed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('logistics.orders.show', $order) }}" class="fw-semibold text-decoration-none" style="color: var(--juvo-ink);">#{{ $order->id }}</a>
                                <div class="text-muted" style="font-size: .66rem;">{{ $order->logistics_tracking_id ?? 'No tracking ID' }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-placeholder" style="width: 28px; height: 28px; font-size: .64rem;">{{ Str::upper(Str::substr($order->customer->first_name ?? 'G', 0, 1)) }}</div>
                                    <div>
                                        <div>{{ $order->customer->first_name ?? 'Guest' }} {{ $order->customer->last_name ?? '' }}</div>
                                        <div class="text-muted" style="font-size: .66rem;">{{ $order->vendor->name ?? 'Marketplace' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>{{ $order->pickupLocation->name ?? 'Pickup unset' }}</div>
                                <div class="text-muted" style="font-size: .66rem;">{{ $order->shippingAddress->city ?? $order->shippingAddress->address ?? 'Destination unset' }}</div>
                            </td>
                            <td>
                                @if ($order->assignedRider)
                                    <div>{{ $order->assignedRider->full_name }}</div>
                                    <div class="text-muted" style="font-size: .66rem;">{{ Str::headline($order->assignedRider->vehicle_type ?? 'rider') }}</div>
                                @else
                                    <span class="badge-status status-pending">Unassigned</span>
                                @endif
                            </td>
                            <td><span class="badge-status status-{{ $order->logistics_status ?? 'pending' }}">{{ $order->logisticsStatusLabel() }}</span></td>
                            <td><span class="badge-status status-{{ $order->logistics_priority === 'urgent' ? 'failed' : ($order->logistics_priority === 'high' ? 'in_transit' : 'pending') }}">{{ Str::headline($order->logistics_priority ?: 'normal') }}</span></td>
                            <td class="text-end fw-semibold">
                                {{ $order->currency ?? 'TZS' }} {{ number_format((float) $order->total_amount, 0) }}
                                @if (in_array($order->payment_method, ['cod', 'payondelivery', 'pod', 'pay_on_delivery'], true))
                                    <div><span class="badge-status status-in_transit mt-1">COD</span></div>
                                @endif
                            </td>
                            <td class="text-muted">{{ $order->created_at->format('d M, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="icon"><i class="fas fa-box-open"></i></div>
                                    <strong>No matching orders</strong>
                                    <p>Try clearing the filters or widening the date range.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="small text-muted">Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}</span>
                {{ $orders->links() }}
            </div>
        @endif
    </div>
@endsection
