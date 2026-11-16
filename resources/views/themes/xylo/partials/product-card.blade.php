{{--
    Reusable Juvo product card.
    Expects: $product (with translation, thumbnail, primaryVariant, reviews_count), $currency
--}}
@php
    $variant = optional($product->primaryVariant);
    $original = $variant->converted_price ?? null;
    $sale = $product->hasActivePromotion() ? ($variant->converted_discount_price ?? null) : null;
    $name = $product->translation->name ?? 'Product';
    $thumb = $product->thumbnail ? Storage::url($product->thumbnail->image_url) : 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320"><rect width="100%" height="100%" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-family="sans-serif" font-size="18">No image</text></svg>';
    $discount = ($sale && $original && $original > 0)
        ? round((1 - ($sale / $original)) * 100)
        : null;
    $current = $sale ?? $original;
@endphp
<div class="jv-product" data-product-id="{{ $product->id }}"
    @isset($recoSurface) data-reco-surface="{{ $recoSurface }}"@endisset
    @isset($recoPosition) data-reco-position="{{ $recoPosition }}"@endisset>
    <div class="jv-product-media">
        <a href="{{ route('product.show', $product->slug) }}">
            <img src="{{ $thumb }}" alt="{{ $name }}" loading="lazy">
        </a>
        @if($product->label)
            <span class="{{ $product->labelBadgeClass() }}">{{ $product->label }}</span>
        @endif
        @if($discount && $discount > 0)
            <span class="jv-badge jv-badge-red">-{{ $discount }}%</span>
        @endif
        <button class="jv-wish wishlist-btn" data-product-id="{{ $product->id }}" aria-label="Save">
            <i class="fa-regular fa-heart"></i>
        </button>
        <button type="button" class="jv-quick-add" data-product-id="{{ $product->id }}" aria-label="Add {{ $name }} to cart">
            <i class="fa-solid fa-plus"></i>
        </button>
    </div>
    <div class="jv-product-body">
        <a href="{{ route('product.show', $product->slug) }}"><h3 class="jv-product-name">{{ $name }}</h3></a>
        <div class="jv-price-row">
            @if($current)
                <span class="jv-price">{{ $currency->symbol }}{{ number_format($current, 2) }}</span>
                @if($sale && $original)
                    <span class="jv-price-old">{{ $currency->symbol }}{{ number_format($original, 2) }}</span>
                @endif
            @else
                <span class="jv-price">N/A</span>
            @endif
        </div>
        @if(isset($product->reviews_count))
            <div class="jv-product-meta">
                <i class="fa-solid fa-star" style="color:var(--jv-yellow);"></i>
                <span>{{ $product->reviews_count }} review{{ $product->reviews_count == 1 ? '' : 's' }}</span>
            </div>
        @endif
    </div>
</div>
