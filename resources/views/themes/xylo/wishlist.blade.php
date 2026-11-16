@extends('themes.xylo.layouts.master')

@section('content')
@php $currency = activeCurrency(); @endphp

<div class="jv-page">
    <div class="jv-container">
        <nav class="jv-breadcrumb">
            <a href="{{ url('/') }}">{{ __('store.category.home') }}</a>
            <i class="fa fa-angle-right"></i>
            <span>{{ __('store.wishlist.title') }}</span>
        </nav>

        <h2 class="jv-page-title">{{ __('store.wishlist.title') }}</h2>

        @if($products->isEmpty())
            <p class="jv-empty">{{ __('store.wishlist.empty') }}</p>
        @else
            <div class="jv-grid">
                @foreach ($products as $product)
                    @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
