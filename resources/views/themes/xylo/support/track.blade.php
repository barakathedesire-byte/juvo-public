@extends('themes.xylo.layouts.master')

@section('content')
<div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-semibold mb-0">Order #{{ $order->id }}</h5>
        <a href="{{ route('support.index') }}" class="btn btn-light btn-sm rounded-pill">
            <i class="bi bi-arrow-left me-1"></i>Back to Support
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm rounded-3" style="border:0.1px solid #e9f8ee;">
                <div class="card-body py-3" style="font-size:14px;">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Status</span>
                        <span class="badge text-bg-info text-uppercase">{{ $order->statusLabel() }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Placed</span>
                        <span>{{ $order->created_at?->format('d M Y H:i') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total</span>
                        <strong>{{ activeCurrency()->symbol ?? 'TSh' }} {{ number_format((float) $order->total_amount, 2) }}</strong>
                    </div>
                    @if ($order->carrier || $order->tracking_number)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Carrier / Tracking</span>
                            <span>{{ $order->carrier }} {{ $order->tracking_number }}</span>
                        </div>
                    @endif

                    <hr>

                    <h6 class="fw-semibold">Items</h6>
                    <ul class="mb-0 ps-3">
                        @foreach ($order->details as $detail)
                            <li>
                                {{ $detail->product_name ?? ($detail->product?->name ?? 'Product') }}
                                × {{ $detail->quantity }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm rounded-3" style="border:0.1px solid #e9f8ee;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Progress</h6>
                </div>
                <div class="card-body py-2" style="font-size:13px;">
                    @include('themes.xylo.partials.order-timeline', ['order' => $order])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
