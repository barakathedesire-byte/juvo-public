@extends('agent.layouts.agent')

@section('title', 'My Orders')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">My Orders</h2>
            <p class="jt-muted mb-0">Track orders attributed to you.</p>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-3 mb-3">
    @foreach ([
        ['Total', $metrics['total'], 'fa-receipt'],
        ['Awaiting', $metrics['awaiting'], 'fa-clock'],
        ['Processing', $metrics['processing'], 'fa-gears'],
        ['Ready', $metrics['ready'], 'fa-box'],
        ['Dispatched', $metrics['dispatched'], 'fa-truck'],
        ['Delivered', $metrics['delivered'], 'fa-check'],
        ['Cancelled', $metrics['cancelled'], 'fa-xmark'],
        ['Returned', $metrics['returned'], 'fa-rotate-left'],
        ['Attention', $metrics['attention'], 'fa-triangle-exclamation'],
    ] as $card)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="jt-card p-3 h-100">
                <div class="jt-muted" style="font-size:12px;">{{ $card[0] }}</div>
                <div class="fw-bold" style="font-size:22px;">{{ $card[1] }}</div>
                <i class="fas {{ $card[2] }} jt-muted"></i>
            </div>
        </div>
    @endforeach
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <form method="GET" action="{{ route('agent.orders.index') }}" class="row g-2">
        <div class="col-md-3">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search order, customer or phone">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach (['pending','accepted','processing','packed','ready_for_pickup','dispatched','delivered','completed','canceled','returned'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="payment_status" class="form-select">
                <option value="">All payment</option>
                @foreach (['pending','paid','failed','refunded'] as $status)
                    <option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="delivery_status" class="form-select">
                <option value="">All delivery</option>
                @foreach (['pending','assigned','accepted','picked_up','in_transit','delivered','failed','returned','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('delivery_status') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
        </div>
        <div class="col-md-2">
            <select name="sort" class="form-select">
                <option value="latest" @selected(request('sort','latest') === 'latest')>Latest</option>
                <option value="total" @selected(request('sort') === 'total')>Total</option>
                <option value="status" @selected(request('sort') === 'status')>Status</option>
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-sm btn-outline-dark">Filter</button>
            <a href="{{ route('agent.orders.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="jt-card p-0">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Items</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Delivery</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>#{{ $order->id }}</td>
                        <td>{{ $order->customer?->name ?: $order->guest_email }}</td>
                        <td>{{ $order->customer?->phone }}</td>
                        <td>{{ $order->details?->count() ?? 0 }}</td>
                        <td>{{ $order->created_at?->format('d M Y') }}</td>
                        <td>{{ number_format((float) $order->total_amount, 2) }}</td>
                        <td>{{ $order->payment_status }} ({{ $order->payment_method }})</td>
                        <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span></td>
                        <td>{{ ucfirst(str_replace('_',' ',$order->logistics_status ?: 'pending')) }}</td>
                        <td><a href="{{ route('agent.orders.show', $order) }}" class="btn btn-sm btn-outline-dark">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center jt-muted py-4">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $orders->links() }}</div>
@endsection
