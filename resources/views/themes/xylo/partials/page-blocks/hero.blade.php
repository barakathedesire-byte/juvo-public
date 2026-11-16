@php
    $s = $block['settings'];
    $imgUrl = fn ($p) => $p ? (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p)) : null;
    $items = $s['items'] ?? [];
    $height = ['auto' => 'jv-blk-hero-auto', 'medium' => 'jv-blk-hero-md', 'tall' => 'jv-blk-hero-tall'][$s['height'] ?? 'medium'] ?? 'jv-blk-hero-md';
@endphp
@if(count($items))
<section class="jv-section jv-hm-hero jv-blk-hero {{ $height }}">
    <div class="jv-hero-slider"
         data-autoplay="{{ !empty($s['autoplay']) ? '1' : '0' }}"
         data-speed="{{ (int) ($s['speed'] ?? 5000) }}"
         data-arrows="{{ !empty($s['arrows']) ? '1' : '0' }}"
         data-dots="{{ !empty($s['dots']) ? '1' : '0' }}">
        @foreach($items as $item)
            @php
                $desktop = $imgUrl($item['image'] ?? null);
                $mobile = $imgUrl($item['mobile_image'] ?? null) ?: $desktop;
                $align = in_array(($item['text_align'] ?? 'left'), ['left', 'center', 'right'], true) ? $item['text_align'] : 'left';
            @endphp
            <div class="jv-hero-slide">
                <div class="jv-hero-slide-inner jv-hero-align-{{ $align }}">
                    <div class="jv-hero-copy">
                        @if(!empty($item['badge']))<span class="jv-hero-badge">{{ $item['badge'] }}</span>@endif
                        @if(!empty($item['title']))<h2 class="jv-hero-title">{{ $item['title'] }}</h2>@endif
                        @if(!empty($item['subtitle']))<p class="jv-hero-sub">{{ $item['subtitle'] }}</p>@endif
                        @if(!empty($item['description']))<p class="jv-hero-desc">{{ $item['description'] }}</p>@endif
                        @if(!empty($item['cta_label']))
                            <a href="{{ $item['cta_url'] ?: route('shop.index') }}" class="jv-btn jv-btn-primary jv-hero-cta">{{ $item['cta_label'] }}</a>
                        @endif
                    </div>
                    @if($desktop)
                        <img class="jv-hero-img jv-hero-img-desktop" src="{{ $desktop }}" alt="{{ $item['title'] ?? 'Promotion' }}">
                    @endif
                    @if($mobile)
                        <img class="jv-hero-img jv-hero-img-mobile" src="{{ $mobile }}" alt="{{ $item['title'] ?? 'Promotion' }}">
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
