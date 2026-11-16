@extends('themes.xylo.layouts.master')
@section('title', optional($product->translation)->name ?? $product->slug)
@section('content')
@php
    $currency = activeCurrency();
    $productName = optional($product->translation)->name ?? $product->slug;
    $primaryVariant = $product->primaryVariant;
    $activePromotions = $product->activePromotions();
    $salePrice = $activePromotions->isNotEmpty() ? $primaryVariant?->converted_discount_price : null;
    $regularPrice = $primaryVariant?->converted_price;
    $hasDiscount = $salePrice && $regularPrice && $salePrice < $regularPrice;
    $appearance = $appearance ?? \App\Services\Pages\ProductPageAppearance::forPage(\App\Models\Page::where('system_key', 'product')->first());
@endphp
<div class="jv-page" style="background:#F5F5F5;" data-product-template="marketplace">
    <div class="jv-container">
        @include('themes.xylo.partials.page-region', ['key' => 'product'])
        @if($appearance['show_breadcrumb'] ?? true)
        <nav class="jv-breadcrumb" style="margin-bottom:16px;">
            <a href="{{ url('/') }}">{{ __('store.product_detail.home') }}</a>
            <i class="fa fa-angle-right"></i>
            @foreach($breadcrumbs as $category)
                <a href="{{ route('category.show', $category->slug) }}">{{ optional($category->translation)->name ?? $category->slug }}</a>
                <i class="fa fa-angle-right"></i>
            @endforeach
            <span>{{ $productName }}</span>
        </nav>
        @endif

        @include('themes.xylo.partials.ad-slot', ['key' => 'product_sidebar', 'limit' => 1])

        <div class="jv-pdp-spec">
            <div class="jv-pdp-main-card">
                <div class="jv-pdp-main-inner">
                    <div class="jv-pdp-gallery-col">
                        @php
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
                        @endphp
                        <div class="jv-main-image" id="jv-main-image" style="width:100%;height:280px;background:#fff;display:flex;align-items:center;justify-content:center;border:1px solid var(--jv-line);border-radius:4px;overflow:hidden;">
                            <img src="{{ $galleryImages->first()['url'] }}" alt="{{ $galleryImages->first()['alt'] }}" style="max-width:100%;max-height:100%;object-fit:contain;">
                        </div>
                        <div class="jv-thumb-strip">
                            <button type="button" class="jv-thumb-prev" id="jv-thumb-prev"><i class="fa fa-chevron-left"></i></button>
                            <div class="jv-thumbs" id="jv-thumbs">
                                @foreach ($galleryImages as $idx => $img)
                                    <img src="{{ $img['url'] }}" alt="{{ $img['alt'] }}" class="jv-thumb {{ $idx===0?'active':'' }}" data-src="{{ $img['url'] }}">
                                @endforeach
                            </div>
                            <button type="button" class="jv-thumb-next" id="jv-thumb-next"><i class="fa fa-chevron-right"></i></button>
                        </div>
                        @if($appearance['show_share'] ?? true)
                        <div class="jv-share-section">
                            <div class="jv-share-heading">SHARE THIS PRODUCT</div>
                            <div class="jv-share-icons">
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener" class="jv-share-icon jv-share-fb" title="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                                <a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($productName) }}" target="_blank" rel="noopener" class="jv-share-icon jv-share-x" title="Share on X"><i class="fab fa-x-twitter"></i></a>
                                <a href="https://wa.me/?text={{ urlencode($productName.' '.url()->current()) }}" target="_blank" rel="noopener" class="jv-share-icon jv-share-wa" title="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                <button type="button" id="jv-copy-link" class="jv-share-icon jv-share-copy" title="Copy link"><i class="fa fa-link"></i></button>
                            </div>
                        </div>
                        @endif
                        @if($appearance['show_report'] ?? true)
                        <div style="margin-top:16px;">
                            <button type="button" class="jv-report-link" data-bs-toggle="modal" data-bs-target="#reportProductModal">
                                <i class="fa fa-flag me-1"></i> Report incorrect product information
                            </button>
                        </div>
                        @endif
                    </div>

                    <div class="jv-pdp-info-col">
                        <div class="jv-badges">
                            @if($vendor && $vendor->isVerified())
                                <span class="jv-badge jv-badge-blue">Official Store</span>
                            @endif
                            <div class="jv-wishlist-top">
                                @auth('customer')
                                    @php $isFavorite = auth('customer')->user()->wishlistProducts()->where('product_id', $product->id)->exists(); @endphp
                                @else
                                    @php $isFavorite = false; @endphp
                                @endauth
                                <button id="test-heart" class="jv-icon-btn wishlist-btn" data-product-id="{{ $product->id }}" title="Save">
                                    <i class="{{ $isFavorite ? 'fa-solid fa-heart' : 'fa-regular fa-heart' }} fs-4"></i>
                                </button>
                            </div>
                        </div>

                        <h1 class="jv-pdp-title" style="font-size:20px;font-weight:700;margin:0 0 10px;line-height:1.3;">{{ $productName }}</h1>

                        @if ($product->brand)
                            <div class="jv-brand-row">
                                <span>Brand: <a href="{{ route('shop.index', ['brand' => $product->brand->id]) }}">{{ optional($product->brand->translation ?? null)->name ?? $product->brand->name }}</a></span>
                                <span class="ms-1">| <a href="{{ route('shop.index', ['brand' => $product->brand->id]) }}">Similar products from {{ optional($product->brand->translation ?? null)->name ?? $product->brand->name }}</a></span>
                            </div>
                        @endif

                        @if($appearance['show_flash_panel'] ?? true)
                        <div class="jv-flash-panel">
                            <div class="jv-flash-header">
                                <span><i class="fa fa-bolt me-1" style="color:#fcd200;"></i> {{ $product->label ?: ($hasDiscount ? 'Special Offer' : 'Best Price') }}</span>
                            </div>
                            <div class="jv-flash-body">
                                <div class="jv-price-row">
                                    <span class="jv-price-large">{{ $currency->symbol }}{{ $salePrice ?? $regularPrice ?? 'N/A' }}</span>
                                    @if ($hasDiscount)
                                        <span class="jv-price-was">{{ $currency->symbol }}{{ $regularPrice }}</span>
                                        <span class="jv-discount-badge">-{{ max(1, (int) round((1 - $salePrice / $regularPrice) * 100)) }}%</span>
                                    @endif
                                </div>
                                <div class="jv-stock-line">
                                    @if ($inStock)
                                        <span>{{ (int) $primaryVariant->stock }} items in stock</span>
                                    @else
                                        <span class="text-danger">Out of stock</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="jv-shipping-line" id="jv-shipping-line" style="display:none;"></div>

                        <div class="jv-rating-row">
                            @php $averageRating = round($product->reviews_avg_rating, 1); @endphp
                            <div>
                                @for ($i = 1; $i <= 5; $i++)
                                    @if ($i <= floor($averageRating))
                                        <i class="fa-solid fa-star" style="color:var(--jv-yellow);"></i>
                                    @elseif ($i - 0.5 == $averageRating)
                                        <i class="fa-solid fa-star-half-alt" style="color:var(--jv-yellow);"></i>
                                    @else
                                        <i class="fa-regular fa-star" style="color:var(--jv-yellow);"></i>
                                    @endif
                                @endfor
                            </div>
                            <a href="#reviews" onclick="document.getElementById('reviews-tab').click(); return true;">({{ $product->reviews_count }} verified ratings)</a>
                        </div>

                        <div class="jv-section-divider"></div>
                        @if ($product->attributeValues->count())
                            <div class="jv-section-heading">VARIATION AVAILABLE</div>
                            <div id="product-attributes" class="jv-variation-wrap">
                                @php $groupedAttributes = $product->attributeValues->groupBy(fn($item) => $item->attribute->id); @endphp
                                @foreach ($groupedAttributes as $attributeId => $values)
                                    @foreach ($values as $index => $value)
                                        @php $inputId = 'attr_'.$attributeId.'_'.$index; @endphp
                                        <input type="radio" name="attribute_{{ $attributeId }}" id="{{ $inputId }}" value="{{ $value->id }}" {{ $index === 0 ? 'checked' : '' }} style="display:none;">
                                        <label for="{{ $inputId }}" class="jv-variant-btn {{ strtolower($values->first()->attribute->name) === 'color' ? 'color-circle' : '' }}" style="{{ strtolower($values->first()->attribute->name) === 'color' ? 'background-color:' . strtolower($value->value) . ';width:32px;height:32px;border-radius:50%;' : '' }}">
                                            @if(strtolower($values->first()->attribute->name) !== 'color')
                                                {{ $value->translated_value }}
                                            @endif
                                        </label>
                                    @endforeach
                                @endforeach
                            </div>
                            <div class="jv-section-divider"></div>
                        @endif

                        <button class="jv-btn-add-cart" onclick="addToCart({{ $product->id }}, '{{ $product->product_type }}')">
                            <i class="fa fa-shopping-bag"></i> Add to cart
                        </button>

                        @if($activePromotions->isNotEmpty())
                            <div class="jv-section-divider"></div>
                            <div class="jv-section-heading">PROMOTIONS</div>
                            @foreach($activePromotions->take(3) as $promo)
                                @php
                                    $promoValue = rtrim(rtrim(number_format((float) $promo->value, 2, '.', ''), '0'), '.');
                                    $promoText = $promo->title ?: ($promo->type === 'percent'
                                        ? $promoValue.'% off'
                                        : $currency->symbol.number_format((float) $promo->value, 2).' off');
                                @endphp
                                <div class="jv-promo-row">
                                    <div class="jv-promo-icon"><i class="fa fa-star"></i></div>
                                    <div class="jv-promo-text">{{ $promoText }}</div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="jv-sidebar">
                @if($appearance['show_delivery_panel'] ?? true)
                <div class="jv-card">
                    <div class="jv-card-header">DELIVERY & RETURNS</div>
                    <div class="jv-delivery-intro">
                        <div>Choose your delivery region and location to see fees and timing.</div>
                    </div>
                    <div class="jv-delivery-heading">Choose your location</div>
                    <select class="jv-select" id="jv-region-select" style="margin-bottom:12px;">
                        @foreach ($regions as $region)
                            <option value="{{ $region->name }}" @selected(($rememberedRegion ?? null) === $region->name)>{{ $region->name }}</option>
                        @endforeach
                    </select>
                    <select class="jv-select" id="jv-location-select" style="margin-bottom:16px;">
                        <option value="">Select location</option>
                    </select>

                    <div class="jv-delivery-option" id="jv-pickup-option" style="display:none;">
                        <div class="jv-delivery-icon"><i class="fa fa-store"></i></div>
                        <div class="jv-delivery-option-content">
                            <div class="jv-delivery-option-title">Pickup Station</div>
                            <div class="jv-delivery-option-fee">Delivery Fees <span id="jv-pickup-fee">—</span></div>
                            <div class="jv-delivery-option-eta" id="jv-pickup-eta"></div>
                        </div>
                    </div>
                    <div class="jv-delivery-option" id="jv-door-option" style="display:none;">
                        <div class="jv-delivery-icon"><i class="fa fa-truck"></i></div>
                        <div class="jv-delivery-option-content">
                            <div class="jv-delivery-option-title">Door Delivery</div>
                            <div class="jv-delivery-option-fee">Delivery Fees <span id="jv-door-fee">—</span></div>
                            <div class="jv-delivery-option-eta" id="jv-door-eta"></div>
                        </div>
                    </div>

                    <div class="jv-section-divider"></div>
                    <div class="jv-delivery-option" style="border-bottom:none;">
                        <div class="jv-delivery-icon"><i class="fa fa-undo"></i></div>
                        <div class="jv-delivery-option-content">
                            <div class="jv-delivery-option-title">Returns & refunds</div>
                            <div class="jv-delivery-option-eta">
                                Covered by Juvo's platform-wide policy.
                                <a href="{{ route('page.show', 'return-policy') }}">Read the return policy</a>
                            </div>
                        </div>
                    </div>
                    <div class="jv-delivery-option" style="border-bottom:none;">
                        <div class="jv-delivery-icon"><i class="fa fa-truck-fast"></i></div>
                        <div class="jv-delivery-option-content">
                            <div class="jv-delivery-option-title">Shipping & delivery</div>
                            <div class="jv-delivery-option-eta">
                                Fulfilled under Juvo's platform-wide policy.
                                <a href="{{ route('page.show', 'shipping-delivery') }}">Read the shipping policy</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($appearance['show_seller_card'] ?? true)
                <div class="jv-card">
                    <div class="jv-card-header">SELLER INFORMATION</div>
                    <div class="jv-seller-card-body">
                        <div class="jv-seller-name">{{ $shop->name ?? optional($vendor)->shop_name ?? optional($vendor)->name ?? 'Juvo Marketplace' }}</div>
                        @if($vendor && $vendor->isVerified())
                            <div class="jv-seller-score">Verified seller</div>
                        @endif
                        @if($sellerUrl ?? false)
                            <a href="{{ $sellerUrl }}" class="jv-report-link">Visit store</a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        @if($appearance['show_sponsored'] ?? true)
        <div class="jv-sponsored-panel">
            <div class="jv-sponsored-heading">Sponsored products</div>
            @include('themes.xylo.partials.ad-slot', ['key' => 'product_detail_sponsored', 'limit' => 6])
        </div>
        @endif

        @if($appearance['show_reviews'] ?? true)
        <div class="jv-reviews" id="reviews">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">{{ __('store.product_detail.description') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">{{ __('store.product_detail.reviews') }} ({{ $product->reviews_count }})</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ask-tab" data-bs-toggle="tab" data-bs-target="#ask" type="button" role="tab">Questions?</button>
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
                                <div id="starWrapper" style="font-size: 1.5rem; line-height: 1; display: inline-block;">
                                    <span class="star" data-value="1" style="color:#ccc; cursor:pointer;">&#9733;</span>
                                    <span class="star" data-value="2" style="color:#ccc; cursor:pointer;">&#9733;</span>
                                    <span class="star" data-value="3" style="color:#ccc; cursor:pointer;">&#9733;</span>
                                    <span class="star" data-value="4" style="color:#ccc; cursor:pointer;">&#9733;</span>
                                    <span class="star" data-value="5" style="color:#ccc; cursor:pointer;">&#9733;</span>
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

                        @if($product->reviews->isEmpty())
                            <p>{{ __('store.product_detail.no_reviews_yet') }}</p>
                        @else
                            <ul class="jv-review-list">
                                @foreach($product->reviews as $review)
                                    @if($review->is_approved)
                                    <li>
                                        <div class="review-customer-info">
                                            @php $reviewerName = optional($review->customer)->name ?? 'Customer'; @endphp
                                            <img src="{{ optional($review->customer)->profile_image ? asset('storage/' . $review->customer->profile_image) : \App\Support\Avatar::initials($reviewerName, 'f68b1e', 45) }}"
                                                alt="{{ $reviewerName }}"
                                                style="width:45px; height:45px; border-radius:50%; object-fit:cover;"/>
                                            <strong>{{ ucwords($reviewerName) }}</strong>
                                            @if($review->is_verified_purchase)
                                                <span class="badge bg-success ms-1">{{ __('store.product_detail.verified_purchase') ?? 'Verified purchase' }}</span>
                                            @endif
                                        </div>
                                        <div class="review-rating">
                                            @for($i = 1; $i <= 5; $i++)
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
                <div class="tab-pane fade" id="ask" role="tabpanel">
                    <h5>Need help with this product?</h5>
                    <p class="text-muted">Our Juvo support team is happy to help with questions about this product, delivery, returns or anything else.</p>
                    <a href="{{ route('support.index') }}" class="btn btn-primary">Contact Juvo Support</a>
                </div>
            </div>
        </div>
        @endif

        @if(($appearance['show_similar'] ?? true) && $similar->count())
            @include('themes.xylo.partials.product-rail', [
                'title' => 'Similar products',
                'products' => $similar,
                'currency' => $currency,
                'icon' => 'fas fa-layer-group',
                'surface' => 'product_similar',
                'seeAllUrl' => $product->category ? url('category/' . $product->category->slug) : null,
            ])
        @endif

        @if (($complementary ?? collect())->count())
            @include('themes.xylo.partials.product-rail', [
                'title' => 'Frequently bought together',
                'products' => $complementary,
                'currency' => $currency,
                'icon' => 'fas fa-people-arrows',
                'surface' => 'product_complementary',
            ])
        @endif
    </div>

    {{-- Sticky mobile add-to-cart bar --}}
    @if($appearance['show_sticky_cart'] ?? true)
    <div class="jv-sticky-cart" id="jvStickyCart">
        <div class="jv-sc-media">
            <img src="{{ $product->thumbnail ? Storage::url($product->thumbnail->image_url) : asset('images/juvo_logo_3d.png') }}" alt="">
        </div>
        <div class="jv-sc-info">
            <span class="jv-sc-name">{{ $productName }}</span>
            <span class="jv-sc-price"><span id="sc-currency">{{ $currency->symbol }}</span><span id="sc-price">{{ $salePrice ?? $regularPrice ?? 'N/A' }}</span></span>
        </div>
        <button type="button" class="jv-sc-btn" onclick="addToCart({{ $product->id }}, '{{ $product->product_type }}')">
            <i class="fa fa-shopping-bag me-1"></i>{{ __('store.product_detail.add_to_cart') }}
        </button>
    </div>
    @endif
</div>

@if($appearance['show_report'] ?? true)
<div class="modal fade" id="reportProductModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('product.report', $product->slug) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Report incorrect product information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Issue Type</label>
                    <select name="issue_type" class="form-select" required>
                        <option value="">Select issue</option>
                        <option value="wrong_description">Wrong description</option>
                        <option value="wrong_image">Wrong image</option>
                        <option value="wrong_price">Wrong price</option>
                        <option value="wrong_specifications">Wrong specifications</option>
                        <option value="out_of_stock">Out of stock (reported)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description (optional)</label>
                    <textarea name="description" rows="4" class="form-control" maxlength="2000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Report</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@section('js')
<script>
$(document).ready(function() {
    $('#test-heart').on('click', function(e) {
        e.preventDefault();
        jvToggleWishlist(this);
    });

    $('#jv-copy-link').on('click', function () {
        var url = '{{ url()->current() }}';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                if (window.toastr) { toastr.success('Link copied to clipboard'); }
            });
        }
    });

    // thumbnail gallery
    $('.jv-thumb').on('click', function() {
        $('.jv-thumb').removeClass('active');
        $(this).addClass('active');
        $('#jv-main-image img').attr('src', $(this).data('src'));
    });
    $('#jv-thumb-prev').on('click', function() {
        var active = $('.jv-thumb.active');
        var prev = active.prev('.jv-thumb');
        if (prev.length) {
            prev.click();
        }
    });
    $('#jv-thumb-next').on('click', function() {
        var active = $('.jv-thumb.active');
        var next = active.next('.jv-thumb');
        if (next.length) {
            next.click();
        }
    });
});

function changeQty(amount) {
    let qtyInput = document.getElementById("qty");
    let currentQty = parseInt(qtyInput.value) || 1;
    let newQty = currentQty + amount;
    if (newQty < 1) newQty = 1;
    qtyInput.value = newQty;
}

function addToCart(productId, product_type) {
    const quantity = parseInt(document.getElementById("qty") ? document.getElementById("qty").value : 1);
    const attributeInputs = document.querySelectorAll('#product-attributes input[type="radio"]:checked');
    let selectedAttributes = [];
    attributeInputs.forEach(input => { selectedAttributes.push(parseInt(input.value)); });

    fetch("{{ route('cart.add') }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: quantity,
            attribute_value_ids: selectedAttributes,
            product_type: product_type
        })
    })
    .then(response => response.json())
    .then(data => {
        toastr.success(data.message);
        let totalCount = Object.values(data.cart || {}).reduce((sum, item) => sum + item.quantity, 0);
        let el = document.getElementById("cart-count");
        if (el) {
            el.textContent = totalCount;
            el.classList.toggle('d-none', totalCount < 1);
        }
    })
    .catch(error => { toastr.error('Could not add to cart'); console.error("Error:", error); });
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const regionSelect = document.getElementById('jv-region-select');
    const locationSelect = document.getElementById('jv-location-select');
    if (!regionSelect || !locationSelect) return;

    const doorFeeEl = document.getElementById('jv-door-fee');
    const pickupFeeEl = document.getElementById('jv-pickup-fee');
    const doorEtaEl = document.getElementById('jv-door-eta');
    const pickupEtaEl = document.getElementById('jv-pickup-eta');
    const doorOption = document.getElementById('jv-door-option');
    const pickupOption = document.getElementById('jv-pickup-option');
    const shippingLine = document.getElementById('jv-shipping-line');
    const currencySymbol = '{{ $currency->symbol }}';
    let rememberedRegion = @json($rememberedRegion ?? null);
    let rememberedLocationId = @json($rememberedLocationId ?? null);

    function rememberLocation(region, locationId) {
        fetch('{{ route('api.locations.remember') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ region: region, location_id: locationId || null })
        }).catch(function () {});
    }

    function loadLocations(region) {
        if (!region) { locationSelect.innerHTML = '<option value="">Select location</option>'; return; }
        fetch('/api/regions/' + encodeURIComponent(region) + '/locations')
            .then(r => r.json())
            .then(data => {
                locationSelect.innerHTML = '<option value="">Select location</option>';
                const locations = data.locations || [];
                locations.forEach(loc => {
                    var opt = document.createElement('option');
                    opt.value = loc.id;
                    opt.dataset.name = loc.name;
                    opt.textContent = loc.name;
                    locationSelect.appendChild(opt);
                });
                if (region === rememberedRegion && rememberedLocationId) {
                    locationSelect.value = String(rememberedLocationId);
                }
                if (!locationSelect.value && locations.length > 0) {
                    locationSelect.value = String(locations[0].id);
                }
                updateDelivery();
            });
    }

    function updateDelivery() {
        var region = regionSelect.value;
        var selected = locationSelect.options[locationSelect.selectedIndex];
        var location = selected ? (selected.dataset.name || selected.textContent) : '';
        if (!region || !location) return;
        fetch('/api/delivery/quote?region=' + encodeURIComponent(region) + '&location=' + encodeURIComponent(location) + '&subtotal=0')
            .then(r => r.json())
            .then(data => {
                if (data.door && data.door.available) {
                    doorFeeEl.textContent = currencySymbol + Number(data.door.fee).toLocaleString();
                    doorEtaEl.textContent = data.door.eta || '';
                    doorOption.style.display = '';
                } else if (doorOption) {
                    doorOption.style.display = 'none';
                }
                if (data.pickup && data.pickup.available) {
                    pickupFeeEl.textContent = currencySymbol + Number(data.pickup.fee).toLocaleString();
                    pickupEtaEl.textContent = data.pickup.eta || '';
                    pickupOption.style.display = '';
                } else if (pickupOption) {
                    pickupOption.style.display = 'none';
                }
                if (data.door && data.door.available) {
                    shippingLine.textContent = 'Delivery Fees ' + currencySymbol + Number(data.door.fee).toLocaleString();
                    shippingLine.style.display = '';
                } else if (shippingLine) {
                    shippingLine.style.display = 'none';
                }
            });
    }

    regionSelect.addEventListener('change', function() {
        rememberedLocationId = null;
        rememberLocation(this.value, null);
        locationSelect.innerHTML = '<option value="">Select location</option>';
        if (doorOption) doorOption.style.display = 'none';
        if (pickupOption) pickupOption.style.display = 'none';
        if (shippingLine) shippingLine.style.display = 'none';
        loadLocations(this.value);
    });
    locationSelect.addEventListener('change', function() {
        var selected = locationSelect.options[locationSelect.selectedIndex];
        rememberLocation(regionSelect.value, selected ? selected.value : null);
        updateDelivery();
    });

    if (regionSelect.value) loadLocations(regionSelect.value);
});
</script>
@if(Session::has('success'))
    <script>toastr.success("{{ session('success') }}");</script>
@endif
@if(Session::has('error'))
    <script>toastr.error("{{ session('error') }}");</script>
@endif
@endsection
