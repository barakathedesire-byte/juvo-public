@php
    $s = $block['settings'];
    $style = trim(($s['bg_color'] ? "background-color:{$s['bg_color']};" : '').($s['text_color'] ? "color:{$s['text_color']};" : ''));
@endphp
<section class="jv-section">
    <div class="jv-blk-countdown" style="{{ $style }}">
        <div class="jv-blk-countdown-copy">
            @if(!empty($s['heading']))<h2 class="jv-blk-countdown-title">{{ $s['heading'] }}</h2>@endif
            @if(!empty($s['subtitle']))<p class="jv-blk-countdown-sub">{{ $s['subtitle'] }}</p>@endif
        </div>
        @if(!empty($s['ends_at']))
            <div class="jv-blk-countdown-timer" data-countdown="{{ \Illuminate\Support\Carbon::parse($s['ends_at'])->toIso8601String() }}">
                <span class="jv-blk-cd-unit"><b data-cd="days">00</b><small>Days</small></span>
                <span class="jv-blk-cd-unit"><b data-cd="hours">00</b><small>Hrs</small></span>
                <span class="jv-blk-cd-unit"><b data-cd="minutes">00</b><small>Min</small></span>
                <span class="jv-blk-cd-unit"><b data-cd="seconds">00</b><small>Sec</small></span>
            </div>
        @endif
        @if(!empty($s['cta_label']))
            <a href="{{ $s['cta_url'] ?: route('shop.index') }}" class="jv-btn jv-btn-primary">{{ $s['cta_label'] }}</a>
        @endif
    </div>
</section>
