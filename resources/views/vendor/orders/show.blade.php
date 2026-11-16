@extends('vendor.layouts.master')

@section('content')
<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h5 class="mb-0">Order #{{ $order->id }}</h5>
    <div>
        <a href="{{ route('vendor.orders.document', [$order->id, 'invoice']) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Invoice</a>
        <a href="{{ route('vendor.orders.document', [$order->id, 'receipt']) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Receipt</a>
        <a href="{{ route('vendor.orders.document', [$order->id, 'packing_slip']) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Packing slip</a>
        <a href="{{ route('vendor.orders.index') }}" class="btn btn-sm btn-outline-primary">Back</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header card-header-bg text-white">
                <h6 class="mb-0">Items · Status: <span class="badge bg-light text-dark">{{ $order->statusLabel() }}</span></h6>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->details as $detail)
                            <tr>
                                <td>{{ optional(optional($detail->product)->translation)->name ?? ('#'.$detail->product_id) }}</td>
                                <td>{{ optional($detail->variant)->sku ?? '—' }}</td>
                                <td class="text-end">{{ $detail->quantity }}</td>
                                <td class="text-end">{{ number_format((float) $detail->price, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $detail->price * $detail->quantity, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Subtotal</th>
                            <th class="text-end">{{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Delivery fee</th>
                            <th class="text-end">{{ $order->currency }} {{ number_format((float) $order->delivery_fee, 2) }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Total</th>
                            <th class="text-end">{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header card-header-bg text-white"><h6 class="mb-0">Status history</h6></div>
            <div class="card-body">
                @forelse ($order->statusHistories->sortByDesc('id') as $history)
                    <div class="border-start ps-3 pb-3">
                        <div><strong>{{ str_replace('_', ' ', $history->to_status) }}</strong>
                            <span class="text-muted small">from {{ $history->from_status ?? '—' }}</span>
                        </div>
                        <div class="text-muted small">
                            {{ $history->created_at?->format('Y-m-d H:i') }} · {{ $history->actor_type ?? 'system' }}
                            @if ($history->reason) · {{ $history->reason }} @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No status changes recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header card-header-bg text-white"><h6 class="mb-0">Customer</h6></div>
            <div class="card-body small">
                @if ($order->customer)
                    <div class="fw-bold">{{ $order->customer->name }}</div>
                    <div>{{ $order->customer->email }}</div>
                    <div>{{ $order->customer->phone }}</div>
                @else
                    <div>Guest: {{ $order->guest_email ?? 'N/A' }}</div>
                @endif
                @if ($order->shippingAddress)
                    <hr>
                    <div class="fw-bold">Ship to</div>
                    <div>{{ $order->shippingAddress->name }}</div>
                    <div>{{ $order->shippingAddress->address }}</div>
                    <div>{{ $order->shippingAddress->city }} {{ $order->shippingAddress->postal_code }} {{ $order->shippingAddress->country }}</div>
                @endif
                <hr>
                <div>Payment: {{ $order->payment_method }} · <span class="badge bg-secondary">{{ $order->payment_status }}</span></div>
                <div class="mt-1">
                    Fulfillment:
                    <span class="badge {{ $order->isPickup() ? 'bg-warning text-dark' : 'bg-primary' }}">
                        {{ $order->isPickup() ? 'Pickup station' : 'Door delivery' }}
                    </span>
                    @if ($order->isPickup() && $order->pickup_location_name)
                        <div class="mt-1">{{ $order->pickup_location_name }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header card-header-bg text-white"><h6 class="mb-0">Update status</h6></div>
            <div class="card-body">
                @if (!empty($transitions))
                    <form action="{{ route('vendor.orders.status', $order->id) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <select name="status" class="form-select" required>
                                @foreach ($transitions as $status)
                                    <option value="{{ $status }}">{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="text" name="reason" class="form-control mb-2" placeholder="Internal note (optional)">
                        <button class="btn btn-primary w-100">Apply</button>
                    </form>
                @else
                    <p class="text-muted mb-0">No further transitions available for this status.</p>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header card-header-bg text-white"><h6 class="mb-0">Tracking</h6></div>
            <div class="card-body">
                <form action="{{ route('vendor.orders.tracking', $order->id) }}" method="POST">
                    @csrf
                    <input type="text" name="shipping_method" class="form-control mb-2" placeholder="Shipping method" value="{{ $order->shipping_method }}">
                    <input type="text" name="carrier" class="form-control mb-2" placeholder="Carrier" value="{{ $order->carrier }}">
                    <input type="text" name="tracking_number" class="form-control mb-2" placeholder="Tracking number" value="{{ $order->tracking_number }}">
                    <button class="btn btn-outline-primary w-100">Save tracking</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
