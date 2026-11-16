@extends('themes.xylo.layouts.master')
@section('title', 'Order Confirmed')
@section('content')
    <div class="jv-page">
        <div class="jv-container">
            <div class="jv-thankyou">
                <div class="jv-thankyou-icon"><i class="fa-solid fa-circle-check"></i></div>
                <h1 class="jv-page-title">{{ __('store.checkout.order_success') }}</h1>
                <p class="jv-thankyou-text">
                    Thank you for shopping with Juvo. We&rsquo;ve received your order and our vendors are getting it ready.
                </p>

                @if (! empty($order))
                    <div class="jv-card mt-4 text-start">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="jv-summary-head mb-0">Order #{{ $order->id }}</h3>
                            <span class="badge text-bg-info text-uppercase">{{ $order->statusLabel() }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Placed</span>
                            <span>{{ $order->created_at?->format('d M Y H:i') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Payment</span>
                            <span>{{ str_replace('_', ' ', (string) $order->payment_status) }}</span>
                        </div>
                        @if ($order->carrier || $order->tracking_number)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Carrier / Tracking</span>
                                <span>{{ $order->carrier }} {{ $order->tracking_number }}</span>
                            </div>
                        @endif

                        <hr>

                        <h6 class="fw-semibold">Tracking</h6>
                        @include('themes.xylo.partials.order-progress', ['order' => $order])
                    </div>
                @endif

                <div class="jv-thankyou-actions">
                    @if (! empty($order))
                        <a href="{{ route('customer.orders.show', $order) }}" class="jv-btn">Track this order</a>
                    @endif
                    <a href="{{ route('xylo.home') }}" class="jv-btn jv-btn-outline">Continue shopping</a>
                </div>
            </div>
        </div>
    </div>
@endsection
