@php($max = max(1, collect($trend)->max('orders') ?? 1))
<div class="ic-bars" role="img" aria-label="Order volume trend">
    @foreach (collect($trend)->take(-21) as $point)
        <div class="bar" title="{{ $point['day'] }}: {{ $point['orders'] }} orders">
            <span style="height:{{ max(3, round(($point['orders'] / $max) * 100)) }}%"></span>
            <small>{{ \Illuminate\Support\Carbon::parse($point['day'])->format('d M') }}</small>
        </div>
    @endforeach
</div>
@if (count($trend) === 0)
    <div class="ic-empty">No order activity in this period.</div>
@endif
