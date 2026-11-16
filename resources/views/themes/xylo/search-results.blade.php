@extends('themes.xylo.layouts.master')

@section('title', 'Search results for "'.$query.'"')

@section('content')
    @php
        $currency = activeCurrency();
        $total = $meta['total'] ?? $products->total();
    @endphp
    <div class="jv-page">
        <div class="container">
            <nav class="jv-breadcrumb">
                <a href="{{ url('/') }}">{{ __('store.category.home') }}</a>
                <i class="fa fa-angle-right"></i>
                <span>Search</span>
            </nav>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="jv-page-title mb-0">Search results for &ldquo;{{ $query }}&rdquo;</h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">{{ $total }} result{{ $total === 1 ? '' : 's' }}</span>
                    <select id="searchSort" class="form-select form-select-sm" style="width:auto;">
                        <option value="">Sort: Relevance</option>
                        <option value="promotion" @selected(($meta['sort'] ?? '') === 'promotion')>Promotions</option>
                        <option value="newest" @selected(($meta['sort'] ?? '') === 'newest')>Newest</option>
                        <option value="price_asc" @selected(($meta['sort'] ?? '') === 'price_asc')>Price: Low to High</option>
                        <option value="price_desc" @selected(($meta['sort'] ?? '') === 'price_desc')>Price: High to Low</option>
                        <option value="rating" @selected(($meta['sort'] ?? '') === 'rating')>Top rated</option>
                    </select>
                </div>
            </div>

            <div class="row g-4">
                <aside class="col-lg-3 d-none d-lg-block">
                    @include('themes.xylo.partials.facets', [
                        'action' => route('search'),
                        'query' => $query,
                        'sort' => $meta['sort'] ?? null,
                        'facets' => $facets,
                        'selected' => $selected,
                    ])
                </aside>

                <div class="col-lg-9">
                    @if (($meta['total'] ?? 0) === 0)
                        <div class="jv-empty text-center py-5">
                            <p class="mb-1">{{ __('store.category.no_products_found') }}</p>
                            @if (!empty($meta['did_you_mean']))
                                <p class="mb-0">Did you mean
                                    <a href="{{ route('search', array_merge(request()->query(), ['q' => $meta['did_you_mean'], 'page' => 1])) }}">
                                        &ldquo;{{ $meta['did_you_mean'] }}&rdquo;
                                    </a>?
                                </p>
                            @endif
                        </div>
                    @else
                        @if ($sponsored->isNotEmpty())
                            <h6 class="text-uppercase text-muted small mb-2">Sponsored</p>
                            <div class="jv-grid mb-4">
                                @foreach ($sponsored as $ad)
                                    @php $sp = $ad->product; @endphp
                                    <div class="jv-card position-relative" data-sponsored="1">
                                        <a href="{{ route('product.show', $sp->slug) }}" class="text-decoration-none">
                                            @if ($product = $sp)
                                                @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                                            @endif
                                        </span>
                                        <span class="jv-ad-tag">Sponsored</span>
                                    </div>
                                    <img src="{{ route('ads.impression', $ad->id) }}" width="1" height="1" alt="" style="position:absolute;left:-9999px;">
                                @endforeach
                            </div>
                        @endif

                        @if ($products->count() > 0)
                            <div class="jv-grid">
                                @include('themes.xylo.partials.product-list')
                            </div>
                            <div class="jv-pagination mt-5">
                                {{ $products->links() }}
                            </div>
                        @elseif ($sponsored->isEmpty())
                            <p class="jv-empty">{{ __('store.category.no_products_found') }}</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
<script>
    (function () {
        var form = document.getElementById('facetSidebar');
        if (form) {
            form.querySelectorAll('.facet-input').forEach(function (input) {
                input.addEventListener('change', function () { form.submit(); });
            });
            ['minPrice', 'maxPrice'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.addEventListener('change', function () { form.submit(); }); }
            });
        }

        var sort = document.getElementById('searchSort');
        if (sort) {
            sort.addEventListener('change', function () {
                var url = new URL(window.location.href);
                if (sort.value) { url.searchParams.set('sort', sort.value); }
                else { url.searchParams.delete('sort'); }
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });
        }
    })();
</script>
@endsection
