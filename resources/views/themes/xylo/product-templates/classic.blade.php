@extends('themes.xylo.layouts.master')
@section('title', optional($product->translation)->name ?? $product->slug)
@section('content')
@php
    $currency = activeCurrency();
    $productName = optional($product->translation)->name ?? $product->slug;
    $primaryVariant = $product->primaryVariant;
    $salePrice = $product->hasActivePromotion() ? $primaryVariant?->converted_discount_price : null;
    $regularPrice = $primaryVariant?->converted_price;
    $hasDiscount = $salePrice && $regularPrice && $salePrice < $regularPrice;
    $averageRating = round((float) ($product->reviews_avg_rating ?? 0), 1);

    $galleryImages = collect();
    foreach ($product->images as $image) {
        if (! empty($image->image_url)) {
            $galleryImages->push(['url' => Storage::url($image->image_url), 'alt' => $image->name ?? $productName]);
        }
    }
    if ($galleryImages->isEmpty() && ! empty(optional($product->thumbnail)->image_url)) {
        $galleryImages->push(['url' => Storage::url($product->thumbnail->image_url), 'alt' => $productName]);
    }
    if ($galleryImages->isEmpty() && ! empty($product->image_url)) {
        $galleryImages->push(['url' => Storage::url($product->image_url), 'alt' => $productName]);
    }
    if ($galleryImages->isEmpty()) {
        $galleryImages->push(['url' => asset('images/juvo_logo_3d.png'), 'alt' => $productName]);
    }

    $isFavorite = false;
    if (auth('customer')->check()) {
        $isFavorite = auth('customer')->user()->wishlistProducts()->where('product_id', $product->id)->exists();
    }
@endphp
<div class="jv-page" data-product-template="classic">
    <div class="jv-container">
        @include('themes.xylo.partials.page-region', ['key' => 'product'])

        <nav class="jv-breadcrumb">
            <a href="{{ url('/') }}">{{ __('store.product_detail.home') }}</a>
            <i class="fa fa-angle-right"></i>
            @foreach($breadcrumbs as $cat)
                <a href="{{ route('category.show', $cat->slug) }}">{{ optional($cat->translation)->name ?? $cat->slug }}</a>
                <i class="fa fa-angle-right"></i>
            @endforeach
            <span>{{ $productName }}</span>
        </nav>

        <div class="jv-pdp">
            <div class="jv-pdp-gallery">
                <div class="product-slider">
                    @foreach ($galleryImages as $img)
                        <div>
                            <img src="{{ $img['url'] }}" alt="{{ $img['alt'] }}">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="jv-pdp-info">
                @if ($inStock)
                    <div id="product-stock" class="jv-stock jv-in-stock">{{ __('store.product_detail.in_stock') }}</div>
                @else
                    <div id="product-stock" class="jv-stock text-danger">{{ __('store.product_detail.out_of_stock') }}</div>
                @endif

                <div class="jv-stars">
                    @for ($i = 1; $i <= 5; $i++)
                        @if ($i <= floor($averageRating))
                            <i class="fa-solid fa-star"></i>
                        @elseif ($i - 0.5 == $averageRating)
                            <i class="fa-solid fa-star-half-alt"></i>
                        @else
                            <i class="fa-regular fa-star"></i>
                        @endif
                    @endfor
                    <span class="jv-stars-label">({{ $product->reviews_count }} {{ __('store.product_detail.customer_reviews') }})</span>
                </div>

                <div class="jv-pdp-title-row">
                    <h1 class="jv-pdp-title">{{ $productName }}</h1>
                    <button id="test-heart" class="jv-icon-btn wishlist-btn" data-product-id="{{ $product->id }}">
                        <i class="{{ $isFavorite ? 'fa-solid fa-heart' : 'fa-regular fa-heart' }} fs-4"></i>
                    </button>
                </div>

                <h2 class="jv-pdp-price">
                    <span id="currency-symbol">{{ $currency->symbol }}</span><span id="variant-price">{{ $salePrice ?? $regularPrice ?? 'N/A' }}</span>
                    @if ($hasDiscount)
                        <span class="text-muted ms-2" style="font-size:16px;text-decoration:line-through;">{{ $currency->symbol }}{{ $regularPrice }}</span>
                        <span class="badge bg-danger ms-2">-{{ max(1, (int) round((1 - $salePrice / $regularPrice) * 100)) }}%</span>
                    @endif
                </h2>
                <p class="jv-pdp-desc">{{ optional($product->translation)->short_description }}</p>

                <div id="product-attributes" class="product-options">
                    @php $groupedAttributes = $product->attributeValues->groupBy(fn ($item) => $item->attribute->id); @endphp
                    @foreach ($groupedAttributes as $attributeId => $values)
                        <div class="attribute-options mt-3">
                            <h3 class="jv-opt-head">{{ __('store.product_detail.' . strtolower($values->first()->attribute->name)) }}</h3>
                            <div class="{{ strtolower($values->first()->attribute->name) }}-wrapper jv-opt-wrap">
                                @foreach ($values as $index => $value)
                                    @php $inputId = strtolower($values->first()->attribute->name) . '-c-' . $index; @endphp
                                    <input type="radio" name="attribute_{{ $attributeId }}" id="{{ $inputId }}" value="{{ $value->id }}" {{ $index === 0 ? 'checked' : '' }}>
                                    <label for="{{ $inputId }}"
                                        class="{{ strtolower($values->first()->attribute->name) === 'color' ? 'color-circle' : 'size-box' }}"
                                        style="{{ strtolower($values->first()->attribute->name) === 'color' ? 'background-color:' . strtolower($value->value) . ';' : '' }}">
                                        @if (strtolower($values->first()->attribute->name) === 'size')
                                            {{ $value->translated_value }}
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="jv-cart-actions">
                    <div class="jv-qty">
                        <button type="button" onclick="changeQty(-1)">&minus;</button>
                        <input type="text" id="qty" value="1">
                        <button type="button" onclick="changeQty(1)">+</button>
                    </div>
                    <button class="jv-btn jv-btn-lg" onclick="addToCart({{ $product->id }}, '{{ $product->product_type }}')">
                        <i class="fa fa-shopping-bag me-2"></i>{{ __('store.product_detail.add_to_cart') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="jv-reviews">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">{{ __('store.product_detail.description') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">{{ __('store.product_detail.reviews') }} ({{ $product->reviews_count }})</button>
                </li>
            </ul>
            <div class="tab-content pt-3" id="myTabContent">
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    {!! \App\Support\Html\HtmlSanitizer::clean(optional($product->translation)->description) !!}
                </div>
                <div class="tab-pane fade" id="reviews" role="tabpanel">
                    <div class="product-detail-customer-review">
                        @auth('customer')
                            <div class="mt-4 mb-4">
                                <h5>{{ __('store.product_detail.submit_review_title') }}</h5>
                                <form action="{{ route('review.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="rating" id="rating-value" required>
                                    <div id="starWrapper" style="font-size:1.5rem;line-height:1;display:inline-block;">
                                        @for ($v = 1; $v <= 5; $v++)
                                            <span class="star" data-value="{{ $v }}" style="color:#ccc;cursor:pointer;">&#9733;</span>
                                        @endfor
                                    </div>
                                    <div class="mb-3 mt-3">
                                        <label class="form-label">{{ __('store.product_detail.review_optional') }}</label>
                                        <textarea name="review" class="form-control" rows="3"></textarea>
                                    </div>
                                    <button class="jv-btn">{{ __('store.product_detail.submit_review_btn') }}</button>
                                </form>
                            </div>
                        @else
                            <p class="mt-3">{{ __('store.product_detail.please') }} <a href="{{ route('customer.login') }}">{{ __('store.product_detail.login') }}</a> {{ __('store.product_detail.submit') }}</p>
                        @endauth

                        @if ($product->reviews->isEmpty())
                            <p>{{ __('store.product_detail.no_reviews_yet') }}</p>
                        @else
                            <ul class="jv-review-list">
                                @foreach ($product->reviews as $review)
                                    @if ($review->is_approved)
                                        <li>
                                            @php $reviewerName = optional($review->customer)->name ?? 'Customer'; @endphp
                                            <div class="review-customer-info">
                                                <strong>{{ ucwords($reviewerName) }}</strong>
                                            </div>
                                            <div class="review-rating">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <span style="color: {{ $i <= $review->rating ? 'gold' : '#ccc' }}">&#9733;</span>
                                                @endfor
                                            </div>
                                            <p>{{ $review->review ?: __('store.product_detail.no_review_text') }}</p>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($similar->count())
            @include('themes.xylo.partials.product-rail', [
                'title' => 'Similar products',
                'products' => $similar,
                'currency' => $currency,
                'icon' => 'fas fa-layer-group',
                'seeAllUrl' => $product->category ? route('category.show', $product->category->slug) : null,
            ])
        @endif
    </div>
</div>
@endsection

@section('js')
<script>
    $(document).ready(function () {
        $('#test-heart').on('click', function (e) { e.preventDefault(); jvToggleWishlist(this); });

        $('.product-slider').slick({
            arrows: true, dots: false, infinite: true, slidesToShow: 1, slidesToScroll: 1,
            prevArrow: '<button type="button" class="slick-prev">&#8592;</button>',
            nextArrow: '<button type="button" class="slick-next">&#8594;</button>',
        });
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const stars = document.querySelectorAll('#starWrapper .star');
    const ratingInput = document.getElementById('rating-value');
    stars.forEach(star => {
        star.addEventListener('click', function () {
            const val = parseInt(this.dataset.value);
            ratingInput.value = val;
            stars.forEach(s => { s.style.color = (parseInt(s.dataset.value) <= val) ? 'gold' : '#ccc'; });
        });
    });
});
</script>
<script>const variantMap = @json($variantMap);</script>
<script>
$(document).ready(function () {
    const productId = {{ $product->id }};

    function selectedIds() {
        let ids = [];
        $('#product-attributes input[type="radio"]:checked').each(function () { ids.push(parseInt(this.value)); });
        return ids.sort((a, b) => a - b);
    }

    function matchVariant(ids) {
        for (const v of variantMap) {
            if (JSON.stringify(v.attributes.slice().sort((a, b) => a - b)) === JSON.stringify(ids)) return v.id;
        }
        return null;
    }

    $('#product-attributes input[type="radio"]').on('change', function () {
        const variantId = matchVariant(selectedIds());
        if (!variantId) { alert('{{ __('store.product_detail.variant_not_available') }}'); return; }

        $.ajax({
            url: '{{ url('/get-variant-price') }}', type: 'GET',
            data: { variant_id: variantId, product_id: productId },
            success: function (r) {
                if (r.success) {
                    $('#variant-price').text(r.price);
                    $('#product-stock').text(r.stock);
                    $('#currency-symbol').text(r.currency_symbol);
                    r.is_out_of_stock ? $('#product-stock').addClass('text-danger') : $('#product-stock').removeClass('text-danger');
                }
            },
            error: function () { alert('{{ __('store.product_detail.something_wrong') }}'); }
        });
    });

    $('#product-attributes input[type="radio"]:checked').trigger('change');
});
</script>
<script>
function changeQty(amount) {
    const inp = document.getElementById('qty');
    let n = (parseInt(inp.value) || 1) + amount;
    if (n < 1) n = 1;
    inp.value = n;
}
function addToCart(productId, product_type) {
    const quantity = parseInt(document.getElementById('qty').value) || 1;
    const ids = [];
    document.querySelectorAll('#product-attributes input[type="radio"]:checked').forEach(i => ids.push(parseInt(i.value)));

    fetch("{{ route('cart.add') }}", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
        body: JSON.stringify({ product_id: productId, quantity: quantity, attribute_value_ids: ids, product_type: product_type })
    })
    .then(r => r.json())
    .then(d => {
        toastr.success(d.message);
        const total = Object.values(d.cart || {}).reduce((s, i) => s + i.quantity, 0);
        const el = document.getElementById('cart-count');
        if (el) el.textContent = total;
    })
    .catch(() => toastr.error('Could not add to cart'));
}
</script>
@endsection
