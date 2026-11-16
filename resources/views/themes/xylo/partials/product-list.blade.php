@php $currency = activeCurrency(); @endphp
@forelse($products as $product)
    @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
@empty
    <p class="jv-empty">{{ __('store.category.no_products_found') }}</p>
@endforelse
