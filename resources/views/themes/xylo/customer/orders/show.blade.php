@extends('themes.xylo.layouts.master')

@section('content')
@php $currency = activeCurrency(); @endphp
<div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="fw-semibold mb-0">Order #{{ $order->id }}</h5>
            <span class="text-muted small">Placed {{ $order->created_at?->format('d M Y, H:i') }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('customer.orders.index') }}" class="btn btn-light btn-sm rounded-pill">
                <i class="bi bi-arrow-left me-1"></i>All orders
            </a>
            <a href="{{ route('customer.orders.invoice', $order->id) }}" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill">
                <i class="bi bi-printer me-1"></i>Invoice
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2" style="font-size:13px;">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm rounded-3" style="border:0.1px solid #e9f8ee;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Order timeline</h6>
                </div>
                <div class="card-body py-3" style="font-size:13px;">
                    <div class="mb-2">
                        <span class="text-muted">Status</span>
                        <span class="badge text-bg-info text-uppercase ms-2">{{ $order->statusLabel() }}</span>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted">Payment</span>
                        <span class="ms-2">{{ str_replace('_', ' ', (string) $order->payment_status) }} · {{ $order->payment_method }}</span>
                    </div>

                    @include('themes.xylo.partials.order-progress', ['order' => $order])

                    <details class="mt-3">
                        <summary class="text-muted small">Activity log</summary>
                        <div class="mt-2">
                            @include('themes.xylo.partials.order-timeline', ['order' => $order])
                        </div>
                    </details>

                    @if ($order->logistics_tracking_id)
                        <hr>
                        <div class="mb-2"><span class="text-muted">Logistics status</span><span class="ms-2">{{ $order->logisticsStatusLabel() }}</span></div>
                        @if ($order->assignedRider)
                            <div class="mb-2"><span class="text-muted">Rider</span><span class="ms-2">{{ $order->assignedRider->full_name }}</span></div>
                        @endif
                        @if ($order->deliveryEvents?->count())
                            <details class="mt-2">
                                <summary class="text-muted small">Delivery progress</summary>
                                <div class="mt-2">
                                    @foreach ($order->deliveryEvents as $event)
                                        <div class="small">{{ optional($event->created_at)->format('d M H:i') }} — {{ $event->type }} {{ $event->to_status ? '→ '.$event->to_status : '' }}</div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endif
                    @if ($order->tracking_number)
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Tracking</span>
                            <span>{{ $order->carrier }} {{ $order->tracking_number }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm rounded-3 mt-3" style="border:0.1px solid #e9f8ee;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">{{ $order->isPickup() ? 'Pickup station' : 'Shipping address' }}</h6>
                </div>
                <div class="card-body py-3" style="font-size:13px;">
                    @if ($order->isPickup() && $order->pickup_location_name)
                        <div class="fw-semibold">{{ $order->pickup_location_name }}</div>
                        <div class="text-muted">Collect your order from this station once it is marked ready for pickup.</div>
                        @if ($order->shippingAddress?->phone)
                            <div class="text-muted">{{ $order->shippingAddress->phone }}</div>
                        @endif
                    @elseif ($order->shippingAddress)
                        <div class="fw-semibold">{{ $order->shippingAddress->name }}</div>
                        <div class="text-muted">{{ $order->shippingAddress->address }}</div>
                        <div class="text-muted">
                            {{ $order->shippingAddress->city }}
                            @if ($order->shippingAddress->country) · {{ $order->shippingAddress->country }} @endif
                        </div>
                        @if ($order->shippingAddress->phone)
                            <div class="text-muted">{{ $order->shippingAddress->phone }}</div>
                        @endif
                    @elseif ($order->customer)
                        <div class="fw-semibold">{{ $order->customer->name }}</div>
                        <div class="text-muted">{{ $order->customer->address ?: '—' }}</div>
                        <div class="text-muted">{{ $order->customer->email }}</div>
                    @else
                        <div class="text-muted">No address on file.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm rounded-3" style="border:0.1px solid #e9f8ee;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Items</h6>
                </div>
                <div class="card-body py-3" style="font-size:14px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->details as $detail)
                                <tr>
                                    <td>{{ $detail->product?->translation?->name ?? __('store.category.product_name_not_available') }}</td>
                                    <td class="text-end">{{ $detail->quantity }}</td>
                                    <td class="text-end">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $detail->price, 2) }}</td>
                                    <td class="text-end">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $detail->price * $detail->quantity, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <hr>

                    <table class="table table-sm mb-0" style="width:auto;margin-left:auto;">
                        <tbody>
                            <tr><td class="text-muted">Subtotal</td><td class="text-end">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->subtotal, 2) }}</td></tr>
                            @if ((float) $order->discount_amount > 0)
                                <tr><td class="text-muted">Discount {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</td><td class="text-end">-{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->discount_amount, 2) }}</td></tr>
                            @endif
                            <tr><td class="text-muted">Tax</td><td class="text-end">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->tax_amount, 2) }}</td></tr>
                            <tr><td class="text-muted">Delivery</td><td class="text-end">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->delivery_fee, 2) }}</td></tr>
                            <tr class="fw-bold"><td>Total</td><td class="text-end">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->total_amount, 2) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
