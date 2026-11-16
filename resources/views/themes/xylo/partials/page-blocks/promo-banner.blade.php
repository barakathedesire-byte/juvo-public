@php
    $s = $block['settings'];
    $img = $s['image'] ?? null;
    $imgUrl = $img ? (\Illuminate\Support\Str::startsWith($img, ['http://', 'https://']) ? $img : \Illuminate\Support\Facades\Storage::url($img)) : null;
    $overlay = ['none' => '', 'light' => 'jv-blk-banner-light', 'dark' => 'jv-blk-banner-dark'][$s['overlay'] ?? 'dark'] ?? 'jv-blk-banner-dark';
    $style = trim(($s['bg_color'] ? "background-color:{$s['bg_color']};" : '').($s['text_color'] ? "color:{$s['text_color']};" : ''));
@endphp
<section class="jv-section">
    <div class="jv-blk-banner jv-blk-align-{{ $s['align'] ?? 'left' }} {{ $overlay }}"
         style="{{ $style }}{{ $imgUrl ? "background-image:url('{$imgUrl}');" : '' }}">
        <div class="jv-blk-banner-copy">
            @if(!empty($s['heading']))<h2 class="jv-blk-banner-title">{{ $s['heading'] }}</h2>@endif
            @if(!empty($s['subtitle']))<p class="jv-blk-banner-sub">{{ $s['subtitle'] }}</p>@endif
            @if(!empty($s['cta_label']))
                <a href="{{ $s['cta_url'] ?: route('shop.index') }}" class="jv-btn jv-btn-primary">{{ $s['cta_label'] }}</a>
            @endif
        </div>
    </div>
</section>
