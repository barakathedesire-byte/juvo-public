@php
    $currency = activeCurrency();
    $selectedCats = array_map('strval', (array) ($selected['category'] ?? []));
    $selectedBrands = array_map('strval', (array) ($selected['brand'] ?? []));
    $boundMin = (float) ($facets['price_min'] ?? 0);
    $boundMax = (float) ($facets['price_max'] ?? 1000);
    $priceMin = ($selected['price_min'] ?? null) !== null ? (float) $selected['price_min'] : $boundMin;
    $priceMax = ($selected['price_max'] ?? null) !== null ? (float) $selected['price_max'] : $boundMax;
@endphp
<form method="GET" action="{{ $action }}" class="jv-sidebar" id="facetSidebar">
    @if (!empty($query ?? null))
        <input type="hidden" name="q" value="{{ $query }}">
    @endif
    @if (!empty($sort ?? null))
        <input type="hidden" name="sort" value="{{ $sort }}">
    @endif

    @if (!empty($facets['brands']))
        <h5 class="jv-side-head">{{ __('store.shop.brands') }}</h5>
        @foreach ($facets['brands'] as $brand)
            <div class="form-check jv-check">
                <input class="form-check-input facet-input" type="checkbox" id="brand-{{ $brand['id'] }}"
                       name="brand[]" value="{{ $brand['id'] }}" @checked(in_array((string) $brand['id'], $selectedBrands, true))>
                <label class="form-check-label" for="brand-{{ $brand['id'] }}">
                    {{ mb_convert_case($brand['name'], MB_CASE_TITLE, 'UTF-8') }}
                </label>
                <span class="text-muted">({{ $brand['count'] }})</span>
            </div>
        @endforeach
    @endif

    @if (!empty($facets['categories']))
        <h5 class="jv-side-head">{{ __('store.shop.categories') }}</h5>
        @foreach ($facets['categories'] as $category)
            <div class="form-check jv-check">
                <input class="form-check-input facet-input" type="checkbox" id="cat-{{ $category['id'] }}"
                       name="category[]" value="{{ $category['id'] }}" @checked(in_array((string) $category['id'], $selectedCats, true))>
                <label class="form-check-label" for="cat-{{ $category['id'] }}">
                    {{ mb_convert_case($category['name'], MB_CASE_TITLE, 'UTF-8') }}
                </label>
                <span class="text-muted">({{ $category['count'] }})</span>
            </div>
        @endforeach
    @endif

    <h5 class="jv-side-head">{{ __('store.shop.price') }}</h5>
    <div class="price-filter mb-3">
        <p id="minPriceText" class="mb-1 text-muted small">Min: {{ $currency }}{{ (int) $priceMin }}</p>
        <p id="maxPriceText" class="mb-1 text-muted small">Max: {{ $currency }}{{ (int) $priceMax }}</p>
        <input type="number" class="form-control form-control-sm mb-2" name="price_min"
               min="{{ (int) $boundMin }}" max="{{ (int) $boundMax }}" value="{{ (int) $priceMin }}" placeholder="Min">
        <input type="number" class="form-control form-control-sm" name="price_max"
               min="{{ (int) $boundMin }}" max="{{ (int) $boundMax }}" value="{{ (int) $priceMax }}" placeholder="Max">
    </div>
</form>
