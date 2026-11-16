@extends('themes.xylo.layouts.master')

@section('content')
<div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-semibold mb-0">{{ __('store.orders.title') }}</h5>
        <a href="{{ route('customer.profile.edit') }}" class="btn btn-light btn-sm rounded-pill">
            <i class="bi bi-person me-1"></i>{{ __('store.profile.title') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2" style="font-size:13px;">{{ session('success') }}</div>
    @endif

    @forelse ($orders as $order)
        @php
            $return = $order->returns->last();
            $canReturn = in_array($order->status, ['delivered', 'completed'], true);
            $openReturn = $return && in_array($return->status, ['requested', 'approved'], true);
        @endphp
        <div class="card shadow-sm rounded-3 mb-3" style="border: 0.1px solid #e9f8ee;">
            <div class="card-body py-2 px-3" style="font-size:13px;">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <strong>{{ __('store.orders.order_number') }}{{ $order->id }}</strong>
                        <span class="text-muted ms-2">{{ $order->created_at?->format('d M Y') }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-info text-uppercase">{{ $order->statusLabel() }}</span>
                        <span class="badge {{ $order->isPickup() ? 'text-bg-warning' : 'text-bg-secondary' }} ms-1">{{ $order->isPickup() ? 'Pickup' : 'Delivery' }}</span>
                        <strong>{{ activeCurrency()->symbol ?? 'TSh' }} {{ number_format((float) $order->total_amount, 2) }}</strong>
                        <a href="{{ route('customer.orders.show', $order->id) }}" class="btn btn-outline-dark btn-sm rounded-pill">
                            <i class="bi bi-receipt me-1"></i>Details
                        </a>
                        <a href="{{ route('customer.orders.invoice', $order->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill">
                            <i class="bi bi-printer me-1"></i>Invoice
                        </a>
                    </div>
                </div>

                <hr class="my-2">

                <ul class="mb-2 ps-3">
                    @foreach ($order->details as $detail)
                        <li>
                            {{ $detail->product_name ?? ($detail->product?->name ?? __('store.category.product_name_not_available')) }}
                            × {{ $detail->quantity }}
                        </li>
                    @endforeach
                </ul>

                @if ($return)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-{{ ['requested' => 'warning', 'approved' => 'primary', 'completed' => 'success', 'rejected' => 'danger', 'failed' => 'danger'][$return->status] ?? 'secondary' }}">
                            @if ($return->status === 'requested')
                                {{ __('store.orders.return_requested') }}
                            @elseif ($return->status === 'approved' || $return->status === 'completed')
                                {{ __('store.orders.return_approved') }}
                            @else
                                {{ __('store.orders.return_rejected') }}
                            @endif
                        </span>
                        <span class="text-muted">({{ $return->created_at?->format('d M Y') }})</span>
                    </div>
                    @if ($return->status === 'rejected' && $return->admin_note)
                        <div class="text-muted mb-2">{{ $return->admin_note }}</div>
                    @endif
                @endif

                @if ($openReturn)
                    <div class="text-muted" style="font-size:12px;">{{ __('store.orders.return_in_progress') }}</div>
                @elseif ($canReturn && ! $return)
                    <button class="btn btn-outline-dark btn-sm rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#return-{{ $order->id }}">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('store.orders.request_return') }}
                    </button>

                    <div class="collapse mt-2" id="return-{{ $order->id }}">
                        <form action="{{ route('customer.orders.return', $order->id) }}" method="POST" class="small">
                            @csrf
                            <label class="form-label mb-1">{{ __('store.orders.return_reason') }}</label>
                            <textarea name="reason" rows="3" required minlength="10" maxlength="1000"
                                class="form-control form-control-sm mb-2"
                                placeholder="{{ __('store.orders.return_reason_placeholder') }}">{{ old('reason') }}</textarea>
                            <button type="submit" class="btn btn-dark btn-sm px-3 py-1">
                                {{ __('store.orders.return_submit') }}
                            </button>
                        </form>
                    </div>
                @elseif (! $canReturn && ! $return)
                    <div class="text-muted" style="font-size:12px;">{{ __('store.orders.return_not_allowed') }}</div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-5">{{ __('store.orders.empty') }}</div>
    @endforelse

    <div class="d-flex justify-content-center">
        {{ $orders->links() }}
    </div>
</div>
@endsection

@section('js')
@if (session('success'))
    <script>
        toastr.success("{{ session('success') }}", "{{ __('store.orders.success') }}", {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-top-right",
            timeOut: 5000
        });
    </script>
@endif
@endsection
