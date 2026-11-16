@php
    $milestones = \App\Support\Orders\OrderProgress::milestones($order);
@endphp
<style>
    .jv-progress{list-style:none;margin:0;padding:0;position:relative;}
    .jv-progress-step{position:relative;display:flex;gap:12px;padding-bottom:18px;}
    .jv-progress-step:last-child{padding-bottom:0;}
    .jv-progress-step::before{content:"";position:absolute;left:7px;top:18px;bottom:0;width:2px;background:#e9ecef;}
    .jv-progress-step:last-child::before{display:none;}
    .jv-progress-dot{flex:0 0 16px;width:16px;height:16px;border-radius:50%;background:#e9ecef;border:3px solid #fff;box-shadow:0 0 0 1px #ced4da;margin-top:1px;z-index:1;}
    .jv-progress-step.done .jv-progress-dot{background:#198754;box-shadow:0 0 0 1px #198754;}
    .jv-progress-step.current .jv-progress-dot{background:var(--jv-orange, #f68b1e);box-shadow:0 0 0 1px var(--jv-orange, #f68b1e);}
    .jv-progress-step.canceled .jv-progress-dot{background:#dc3545;box-shadow:0 0 0 1px #dc3545;}
    .jv-progress-label{font-weight:600;font-size:13px;color:#212529;}
    .jv-progress-step.pending .jv-progress-label{color:#6c757d;}
    .jv-progress-meta{color:#6c757d;font-size:12px;}
    .jv-progress-step.current .jv-progress-label{color:var(--jv-orange, #f68b1e);}
</style>

<div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-muted small">Fulfillment</span>
    <span class="badge {{ $order->isPickup() ? 'text-bg-warning' : 'text-bg-primary' }}">
        {{ $order->isPickup() ? 'Pickup station' : 'Door delivery' }}
    </span>
</div>
@if ($order->isPickup() && $order->pickup_location_name)
    <div class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $order->pickup_location_name }}</div>
@endif

<ol class="jv-progress">
    @foreach ($milestones as $milestone)
        <li class="jv-progress-step {{ $milestone['state'] }}">
            <span class="jv-progress-dot"></span>
            <div>
                <div class="jv-progress-label">
                    {{ $milestone['label'] }}
                    @if ($milestone['state'] === 'current')
                        <span class="badge text-bg-primary ms-1">Current</span>
                    @endif
                </div>
                @if ($milestone['at'])
                    <div class="jv-progress-meta">{{ $milestone['at']->format('d M Y, H:i') }}</div>
                @endif
            </div>
        </li>
    @endforeach
</ol>
