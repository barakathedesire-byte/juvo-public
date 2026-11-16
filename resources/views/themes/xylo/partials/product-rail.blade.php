@php
    $products = $products ?? collect();
    $surface = $surface ?? null;
@endphp
@if($products->count())
<section class="jv-section" @if($surface) data-reco-surface="{{ $surface }}" @endif>
    <div class="jv-section-head">
        <h2 class="jv-section-title">
            @if(!empty($icon))<i class="{{ $icon }} me-2" style="color:var(--jv-orange);"></i>@endif
            {{ $title }}
        </h2>
        @if(!empty($seeAllUrl))
            <a href="{{ $seeAllUrl }}" class="jv-seeall">See all →</a>
        @endif
    </div>
    <div class="jv-product-slider jv-rail">
        @foreach($products as $product)
            @include('themes.xylo.partials.product-card', [
                'product' => $product,
                'currency' => $currency,
                'recoSurface' => $surface,
                'recoPosition' => $loop->iteration,
            ])
        @endforeach
    </div>
</section>
@if($surface)
    @include('themes.xylo.partials.recommendation-tracker')
@endif
@endif
