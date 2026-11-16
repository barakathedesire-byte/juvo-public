@php
    $entries = $order->statusHistories->sortBy('created_at')->values();
    $label = fn (?string $status) => ucwords(str_replace('_', ' ', (string) $status));
@endphp
<style>
    .jv-timeline{list-style:none;margin:0;padding:0;position:relative;}
    .jv-timeline::before{content:"";position:absolute;left:7px;top:6px;bottom:6px;width:2px;background:#e9ecef;}
    .jv-timeline li{position:relative;padding-left:26px;padding-bottom:16px;}
    .jv-timeline li:last-child{padding-bottom:0;}
    .jv-timeline li::before{content:"";position:absolute;left:0;top:3px;width:16px;height:16px;border-radius:50%;background:#0d6efd;border:3px solid #fff;box-shadow:0 0 0 1px #0d6efd;}
    .jv-timeline li.done::before{background:#198754;box-shadow:0 0 0 1px #198754;}
    .jv-timeline .tl-title{font-weight:600;font-size:13px;}
    .jv-timeline .tl-meta{color:#6c757d;font-size:12px;}
</style>

<ol class="jv-timeline">
    @forelse ($entries as $entry)
        <li class="{{ $entry->to_status === $order->status ? 'done' : '' }}">
            <div class="tl-title">
                {{ $label($entry->to_status) }}
                @if ($entry->to_status === $order->status)
                    <span class="badge text-bg-primary ms-1">Current</span>
                @endif
            </div>
            <div class="tl-meta">
                {{ $entry->created_at?->format('d M Y, H:i') }}
                @if ($entry->reason) · {{ $entry->reason }} @endif
            </div>
        </li>
    @empty
        <li class="done">
            <div class="tl-title">Order placed</div>
            <div class="tl-meta">{{ $order->created_at?->format('d M Y, H:i') }}</div>
        </li>
    @endforelse
</ol>
