@extends('themes.xylo.layouts.master')
@section('content')
    @php $currency = activeCurrency(); @endphp
    <div class="jv-page">
        <div class="jv-container">
            @include('themes.xylo.partials.page-region', ['key' => 'shop'])
            <nav class="jv-breadcrumb">
                <a href="{{ url('/') }}">{{ __('store.category.home') }}</a>
                <i class="fa fa-angle-right"></i>
                <span>{{ __('store.shop.categories') }}</span>
            </nav>

            <div class="row g-4">
                <aside class="col-lg-3 d-none d-lg-block">
                    <div class="jv-sidebar" id="filterSidebar">
                        <h5 class="jv-side-head">{{ __('store.shop.brands') }}</h5>
                        @foreach($brands as $brand)
                        <div class="form-check jv-check">
                            <input class="form-check-input filter-input" type="checkbox" name="brand[]" value="{{ $brand->id }}"
                                   @checked(in_array((string) $brand->id, array_map('strval', (array) $filters['brand']), true))>
                            <label class="form-check-label">
                                {{ mb_convert_case($brand->translation->name ?? $brand->slug, MB_CASE_TITLE, "UTF-8") }}
                            </label>
                            <span class="text-muted">({{ $brand->products_count }})</span>
                        </div>
                        @endforeach

                        <h5 class="jv-side-head">{{ __('store.shop.categories') }}</h5>
                        @foreach($categories as $category)
                        <div class="form-check jv-check">
                            <input class="form-check-input filter-input" type="checkbox" name="category[]" value="{{ $category->id }}"
                                   @checked(in_array((string) $category->id, array_map('strval', (array) $filters['category']), true))>
                            <label class="form-check-label">
                                {{ mb_convert_case($category->translation->name ?? $category->slug, MB_CASE_TITLE, "UTF-8") }}
                            </label>
                            <span class="text-muted">({{ $category->products_count }})</span>
                        </div>
                        @endforeach

                        <h5 class="jv-side-head">{{ __('store.shop.price') }}</h5>
                        <div class="price-filter mb-3">
                            <p id="priceRange" class="text-center">{{ $currency->symbol }}<span id="minPriceText">{{ (int) ($filters['price_min'] ?: $priceBounds['min']) }}</span> - {{ $currency->symbol }}<span id="maxPriceText">{{ (int) ($filters['price_max'] ?: $priceBounds['max']) }}</span></p>
                            <div class="range-slider">
                                <input type="range" name="price_min" id="minPrice" min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" value="{{ (int) ($filters['price_min'] ?: $priceBounds['min']) }}" step="1">
                                <input type="range" name="price_max" id="maxPrice" min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" value="{{ (int) ($filters['price_max'] ?: $priceBounds['max']) }}" step="1">
                            </div>
                        </div>

                        @if($colors->isNotEmpty())
                        <h5 class="jv-side-head">{{ __('store.shop.colors') }}</h5>
                        @foreach($colors as $color)
                        <div class="form-check jv-check">
                            <input class="form-check-input filter-input" type="checkbox" id="color-{{ $color->id }}" name="color[]" value="{{ $color->value }}"
                                   @checked(in_array((string) $color->value, array_map('strval', (array) $filters['color']), true))>
                            <label class="form-check-label" for="color-{{ $color->id }}">{{ $color->translated_value ?? $color->value }}</label>
                        </div>
                        @endforeach
                        @endif

                        @if($sizes->isNotEmpty())
                        <h5 class="jv-side-head">{{ __('store.shop.size') }}</h5>
                        @foreach($sizes as $size)
                        <div class="form-check jv-check">
                            <input class="form-check-input filter-input" type="checkbox" id="size-{{ $size->id }}" name="size[]" value="{{ $size->value }}"
                                   @checked(in_array((string) $size->value, array_map('strval', (array) $filters['size']), true))>
                            <label class="form-check-label" for="size-{{ $size->id }}">{{ $size->translated_value ?? $size->value }}</label>
                        </div>
                        @endforeach
                        @endif
                    </div>
                </aside>

                <div class="col-lg-9">
                    <div class="jv-grid" id="productList">
                        @include('themes.xylo.partials.product-list')
                    </div>
                    <div class="jv-pagination mt-5">
                        {{ $products->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
<script>
    const minSlider = document.getElementById('minPrice');
    const maxSlider = document.getElementById('maxPrice');
    const minPriceText = document.getElementById('minPriceText');
    const maxPriceText = document.getElementById('maxPriceText');

    function updatePriceDisplay() {
        let minVal = parseInt(minSlider.value);
        let maxVal = parseInt(maxSlider.value);

        if (minVal > maxVal) {
            [minVal, maxVal] = [maxVal, minVal];
        }

        minPriceText.textContent = minVal;
        maxPriceText.textContent = maxVal;

        sendFilterRequest();
    }

    minSlider.addEventListener('input', updatePriceDisplay);
    maxSlider.addEventListener('input', updatePriceDisplay);

    function sendFilterRequest() {
        let url = new URL("{{ route('shop.index') }}", window.location.origin);
        let params = new URLSearchParams();

        document.querySelectorAll('.filter-input:checked').forEach(checked => {
            params.append(checked.name, checked.value);
        });

        let minVal = parseInt(minSlider.value);
        let maxVal = parseInt(maxSlider.value);

        if (minVal > maxVal) {
            [minVal, maxVal] = [maxVal, minVal];
        }

        params.append('price_min', minVal);
        params.append('price_max', maxVal);

        url.search = params.toString();

        fetch(url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            document.getElementById('productList').innerHTML = html;
        });
    }

    document.querySelectorAll('.filter-input').forEach(input => {
        input.addEventListener('change', sendFilterRequest);
    });

    updatePriceDisplay();
</script>
@endsection
