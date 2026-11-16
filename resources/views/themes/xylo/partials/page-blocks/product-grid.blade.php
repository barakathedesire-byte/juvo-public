@php
    $s = $block['settings'];
    $products = $block['data']['products'] ?? collect();
    $cols = (int) ($s['columns'] ?? 4);
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
    <div class="jv-blk-prodgrid" style="--cols:{{ $cols }}">
        @foreach($products as $product)
            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
        @endforeach
    </div>
</section>
@endif
