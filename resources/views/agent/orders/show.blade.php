@extends('agent.layouts.agent')

@section('title', 'Order Details')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">Order #{{ $order->id }}</h2>
            <p class="jt-muted mb-0">{{ $order->created_at?->format('d M Y H:i') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('agent.orders.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
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
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Customer Information</h5>
            <div><strong>Name:</strong> {{ $order->customer?->name ?: $order->guest_email }}</div>
            <div><strong>Phone:</strong> {{ $order->customer?->phone ?: $order->shippingAddress?->phone }}</div>
            <div><strong>Delivery:</strong> {{ $order->shippingAddress?->address }}, {{ $order->shippingAddress?->city }}, {{ $order->shippingAddress?->state }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Order Summary</h5>
            <div><strong>Subtotal:</strong> {{ number_format((float) $order->subtotal, 2) }}</div>
            <div><strong>Delivery:</strong> {{ number_format((float) $order->delivery_fee, 2) }}</div>
            <div><strong>Tax:</strong> {{ number_format((float) $order->tax_amount, 2) }}</div>
            <div><strong>Discount:</strong> {{ number_format((float) $order->discount_amount, 2) }}</div>
            <div><strong>Total:</strong> {{ number_format((float) $order->total_amount, 2) }}</div>
            <div><strong>Payment:</strong> {{ $order->payment_status }} ({{ $order->payment_method }})</div>
            <div><strong>Status:</strong> {{ ucfirst(str_replace('_',' ',$order->status)) }}</div>
            <div><strong>Delivery status:</strong> {{ ucfirst(str_replace('_',' ',$order->logistics_status ?: 'pending')) }}</div>
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Products</h5>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Product</th><th>Variant</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
            <tbody>
                @foreach ($order->details as $detail)
                    <tr>
                        <td>{{ $detail->product?->translation?->name ?: 'Product #'.$detail->product_id }}</td>
                        <td>{{ $detail->variant?->SKU ?: '—' }}</td>
                        <td>{{ $detail->quantity }}</td>
                        <td>{{ number_format((float) $detail->price, 2) }}</td>
                        <td>{{ number_format((float) $detail->price * $detail->quantity, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Lifecycle Timeline</h5>
    @forelse ($order->statusHistories as $history)
        <div class="mb-2">
            <span class="jt-muted" style="font-size:12px;">{{ $history->created_at?->format('d M Y H:i') }}</span>
            <div>{{ ucfirst(str_replace('_',' ',$history->from_status ?: '—')) }} → {{ ucfirst(str_replace('_',' ',$history->to_status)) }}</div>
        </div>
    @empty
        <div class="jt-muted">No status history available.</div>
    @endforelse
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Attribution</h5>
    <div><strong>Salesperson:</strong> {{ $attribution?->rep?->name ?: '—' }}</div>
    <div><strong>Commission rate:</strong> {{ $attribution?->commission_rate }}</div>
    <div><strong>Commission amount:</strong> {{ number_format((float) ($attribution?->commission_amount ?? 0), 2) }}</div>
    <div><strong>Status:</strong> {{ $attribution?->status ?: '—' }}</div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Communication</h5>
    <div class="d-flex gap-2 flex-wrap">
        @if ($order->customer?->phone)
            <a href="tel:{{ $order->customer->phone }}" class="btn btn-sm btn-outline-dark">Call</a>
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $order->customer->phone) }}" target="_blank" class="btn btn-sm btn-outline-success">WhatsApp</a>
        @endif
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ $order->id }}')">Copy Reference</button>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Report Issue</h5>
    <form method="POST" action="{{ route('agent.orders.issues.store', $order) }}">
        @csrf
        <select name="issue_type" class="form-select mb-2" required>
            <option value="delayed_delivery">Delayed delivery</option>
            <option value="rider_not_assigned">Rider not assigned</option>
            <option value="failed_delivery">Failed delivery</option>
            <option value="customer_unavailable">Customer unavailable</option>
            <option value="incorrect_products">Incorrect/missing products</option>
            <option value="damaged_products">Damaged products</option>
            <option value="payment_concern">Payment concern</option>
            <option value="customer_complaint">Customer complaint</option>
            <option value="other">Other</option>
        </select>
        <textarea name="description" class="form-control mb-2" rows="3" required placeholder="Describe the issue"></textarea>
        <button class="btn btn-sm btn-outline-danger">Report Issue</button>
    </form>

    @if ($issues->count())
        <div class="mt-3">
            <h6>Existing Issues</h6>
            @foreach ($issues as $issue)
                <div class="border rounded p-2 mb-2">
                    <div><strong>{{ $issue->ticket_number }}</strong> — {{ $issue->status }}</div>
                    <div class="jt-muted" style="font-size:12px;">{{ $issue->created_at?->format('d M Y H:i') }}</div>
                    <div>{{ $issue->resolution_notes }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="jt-card p-3">
            <h5 class="mb-2">Request Return</h5>
            <form method="POST" action="{{ route('agent.orders.returns.store', $order) }}">
                @csrf
                <textarea name="reason" class="form-control mb-2" rows="2" required placeholder="Reason for return"></textarea>
                <button class="btn btn-sm btn-outline-warning">Submit Return Request</button>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3">
            <h5 class="mb-2">Request Cancellation</h5>
            <form method="POST" action="{{ route('agent.orders.cancellations.store', $order) }}">
                @csrf
                <textarea name="reason" class="form-control mb-2" rows="2" required placeholder="Reason for cancellation"></textarea>
                <button class="btn btn-sm btn-outline-danger">Submit Cancellation Request</button>
            </form>
        </div>
    </div>
</div>
@endsection
