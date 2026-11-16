@extends('themes.xylo.layouts.master')

@section('title', $shop->name)

@section('content')
    @php $currency = activeCurrency(); @endphp
    <div class="jv-page">
        @include('themes.xylo.partials.store-header', ['active' => 'home'])

        <div class="jv-container">
            @if($shop->description)
                <p class="jv-store-desc">{{ $shop->description }}</p>
            @endif

            <div class="row g-3 mb-4">
                @if(optional($vendor->opening_hours)['summary'])
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-bold mb-1"><i class="fa-regular fa-clock me-1"></i> Opening hours</div>
                            <div class="text-muted small">{{ $vendor->opening_hours['summary'] }}</div>
                        </div>
                    </div>
                @endif
                @if(optional($vendor->delivery_coverage)['summary'])
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-bold mb-1"><i class="fa fa-truck me-1"></i> Delivery coverage</div>
                            <div class="text-muted small">{{ $vendor->delivery_coverage['summary'] }}</div>
                        </div>
                    </div>
                @endif
                @if($shop->support_phone || $vendor->phone)
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-bold mb-1"><i class="fa fa-headset me-1"></i> Support</div>
                            <div class="text-muted small"><a href="{{ route('support.index') }}">Contact Juvo Support</a></div>
                        </div>
                    </div>
                @endif
            </div>

            @if(!empty($blocks))
                <div class="jv-page-builder jv-page-blocks jv-pb-cols mb-4">
                    @include('themes.xylo.partials.page-blocks.render', ['blocks' => $blocks, 'currency' => $currency])
                </div>
            @else
                @if($categories->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @foreach($categories as $category)
                            <a href="{{ route('store.products', $shop->slug) }}?category={{ $category->slug }}"
                               class="badge text-bg-light border text-decoration-none">
                                {{ $category->translation->name ?? $category->slug }}
                            </a>
                        @endforeach
                    </div>
                @endif

                @if($featured->isNotEmpty())
                    <h2 class="jv-page-title">Featured</h2>
                    <div class="jv-grid mb-5">
                        @foreach($featured as $product)
                            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                        @endforeach
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="jv-page-title">{{ __('store.category.products') ?? 'Products' }}</h2>
                    <a href="{{ route('store.products', $shop->slug) }}" class="small">View all <i class="fa fa-angle-right"></i></a>
                </div>

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
            @endif

            <div class="jv-store-policies mt-4">
                <a href="{{ route('page.show', 'shipping-delivery') }}" class="jv-store-policy-link">
                    <i class="fa-solid fa-truck-fast"></i> Shipping & delivery policy
                </a>
                <a href="{{ route('page.show', 'return-policy') }}" class="jv-store-policy-link">
                    <i class="fa-solid fa-rotate-left"></i> Returns & refunds policy
                </a>
                <p class="text-muted small mt-2 mb-0">
                    All orders on Juvo follow the platform-wide policies set by Juvo.
                </p>
            </div>
        </div>
    </div>
@endsection
