@extends('themes.xylo.layouts.master')

@section('title', $category->translation->name)

@section('content')
@php $currency = activeCurrency(); @endphp
<div class="jv-page">
    <div class="jv-container">
        @include('themes.xylo.partials.page-region', ['key' => 'category'])
        <nav class="jv-breadcrumb">
            <a href="{{ route('xylo.home') }}">{{ __('store.category.home') }}</a>
            <i class="fa fa-angle-right"></i>
            @foreach($breadcrumbs as $crumb)
                <a href="{{ route('category.show', $crumb->slug) }}">{{ $crumb->translation->name }}</a>
                <i class="fa fa-angle-right"></i>
            @endforeach
            <span>{{ $category->translation->name }}</span>
        </nav>

        <h2 class="jv-page-title">{{ $category->translation->name }}</h2>

        <form method="GET" class="jv-filter-bar">
            <input type="number" name="min_price" class="form-control" placeholder="{{ __('store.category.min_price') }}" value="{{ request('min_price') }}">
            <input type="number" name="max_price" class="form-control" placeholder="{{ __('store.category.max_price') }}" value="{{ request('max_price') }}">
            <select name="sort" class="form-select">
                <option value="">{{ __('store.category.sort_by') }}</option>
                <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>{{ __('store.category.newest') }}</option>
                <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>{{ __('store.category.price_low_high') }}</option>
                <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>{{ __('store.category.price_high_low') }}</option>
                <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>{{ __('store.category.top_rated') }}</option>
            </select>
            <button type="submit" class="jv-btn">{{ __('store.category.filter') }}</button>
        </form>

        @include('themes.xylo.partials.ad-slot', ['key' => 'category_top', 'limit' => 2])

        <div class="jv-grid">
            @forelse ($products as $product)
                @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
            @empty
                <p class="jv-empty">{{ __('store.category.no_products_found') }}</p>
            @endforelse
        </div>

        <div class="jv-pagination mt-5">
            {{ $products->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
