@extends('themes.xylo.layouts.master')

@section('title', $shop->name.' — Products')

@section('content')
    @php $currency = activeCurrency(); @endphp
    <div class="jv-page">
        @include('themes.xylo.partials.store-header', ['active' => 'products'])

        <div class="jv-container">
            @if(!empty($blocks))
                <div class="jv-page-builder jv-page-blocks jv-pb-cols mb-4">
                    @include('themes.xylo.partials.page-blocks.render', ['blocks' => $blocks, 'currency' => $currency])
                </div>
            @endif

            <form method="GET" action="{{ route('store.products', $shop->slug) }}" class="row g-2 align-items-end mb-4">
                <div class="col-md-5">
                    <label class="form-label small mb-1">Search</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                           placeholder="Search in {{ $shop->name }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->slug }}" {{ request('category') === $category->slug ? 'selected' : '' }}>
                                {{ $category->translation->name ?? $category->slug }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Sort</label>
                    <select name="sort" class="form-select">
                        @foreach(['newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'popular' => 'Most popular'] as $value => $label)
                            <option value="{{ $value }}" {{ request('sort') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>

            <p class="text-muted small">{{ $products->total() }} product(s)</p>

            <div class="jv-grid">
                @forelse ($products as $product)
                    @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => activeCurrency()])
                @empty
                    <p class="jv-empty">No products match your filters.</p>
                @endforelse
            </div>

            <div class="jv-pagination mt-5">
                {{ $products->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
