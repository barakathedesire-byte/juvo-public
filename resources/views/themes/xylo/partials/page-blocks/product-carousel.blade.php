@php
    $s = $block['settings'];
    $products = $block['data']['products'] ?? collect();
    $bgColor = $s['bg_color'] ?? null;
    $textColor = $s['text_color'] ?? null;
    $cardStyle = $bgColor ? '--jv-card-bg:'.e($bgColor).';'.($textColor ? '--jv-card-fg:'.e($textColor).';' : '') : null;
@endphp
@if($products->count())
<section class="jv-section{{ $bgColor ? ' jv-section-card' : '' }}" aria-label="{{ $s['title'] ?? 'Products' }}" @if($cardStyle) style="{{ $cardStyle }}" @endif>
    @if(!empty($s['title']) || !empty($s['subtitle']))
        <div class="jv-section-head">
            <div class="jv-section-heading">
                @if(!empty($s['title']))<h2 class="jv-section-title">{{ $s['title'] }}</h2>@endif
                @if(!empty($s['subtitle']))<p class="jv-section-sub">{{ $s['subtitle'] }}</p>@endif
            </div>
            <a href="{{ route('shop.index') }}" class="jv-seeall">See all →</a>
        </div>
    @endif
    <div class="jv-product-slider jv-rail"
         data-arrows="{{ !empty($s['arrows']) ? '1' : '0' }}"
         data-dots="{{ !empty($s['dots']) ? '1' : '0' }}"
         data-autoplay="{{ !empty($s['autoplay']) ? '1' : '0' }}">
        @foreach($products as $product)
            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
        @endforeach
    </div>
</section>
@endif
