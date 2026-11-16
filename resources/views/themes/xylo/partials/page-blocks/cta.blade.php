@php
    $s = $block['settings'];
    $style = trim(($s['bg_color'] ? "background-color:{$s['bg_color']};" : '').($s['text_color'] ? "color:{$s['text_color']};" : ''));
@endphp
<section class="jv-section">
    <div class="jv-blk-cta jv-blk-text-{{ $s['align'] ?? 'center' }}" style="{{ $style }}">
        @if(!empty($s['heading']))<h2 class="jv-blk-cta-title">{{ $s['heading'] }}</h2>@endif
        @if(!empty($s['subtitle']))<p class="jv-blk-cta-sub">{{ $s['subtitle'] }}</p>@endif
        @if(!empty($s['button_label']))
            <a href="{{ $s['button_url'] ?: route('shop.index') }}" class="jv-btn jv-btn-primary jv-btn-lg">{{ $s['button_label'] }}</a>
        @endif
    </div>
</section>
