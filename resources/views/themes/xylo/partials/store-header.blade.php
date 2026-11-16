@php
    $logo = $shop->logo
        ? Storage::disk('public')->url($shop->logo)
        : ($vendor->profile_image ? Storage::disk('public')->url($vendor->profile_image) : asset('images/juvo_logo_3d.png'));
    $banner = $shop->banner
        ? Storage::disk('public')->url($shop->banner)
        : ($vendor->banner ? Storage::disk('public')->url($vendor->banner) : null);
    $active = $active ?? 'home';
    $tabs = [
        'home' => ['label' => 'Home', 'route' => 'store.show'],
        'products' => ['label' => 'Products', 'route' => 'store.products'],
        'collections' => ['label' => 'Collections', 'route' => 'store.collections'],
        'about' => ['label' => 'About', 'route' => 'store.about'],
    ];
    $policyLinks = [
        ['label' => 'Shipping', 'slug' => 'shipping-delivery'],
        ['label' => 'Returns', 'slug' => 'return-policy'],
    ];
@endphp

@if($banner)
    <div class="jv-store-banner" style="background-image:url('{{ $banner }}');height:220px;background-size:cover;background-position:center;"></div>
@endif

<div class="jv-container">
    <nav class="jv-breadcrumb">
        <a href="{{ route('xylo.home') }}">{{ __('store.category.home') }}</a>
        <i class="fa fa-angle-right"></i>
        <span>{{ $shop->name }}</span>
    </nav>

    @if($shop->vacation_mode)
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="fa-solid fa-umbrella-beach me-2"></i>
            <div>{{ $shop->vacation_note ?: 'This store is temporarily paused. Orders may be delayed.' }}</div>
        </div>
    @endif

    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <img src="{{ $logo }}" alt="{{ $shop->name }}" style="width:88px;height:88px;object-fit:cover;border-radius:12px;border:1px solid #eee;">
        <div>
            <h1 class="h4 mb-1">{{ $shop->name }}</h1>
            <div class="text-muted small">
                @if($ratingCount > 0)
                    <span class="text-warning"><i class="fa fa-star"></i> {{ number_format((float) $rating, 1) }}</span>
                    <span>({{ $ratingCount }} {{ __('store.reviews') ?? 'reviews' }})</span>
                @else
                    <span>No reviews yet</span>
                @endif
                @if($vendor->address) · {{ $vendor->address }} @endif
            </div>
        </div>
    </div>

    <ul class="nav nav-pills jv-store-tabs mb-4 flex-wrap gap-2">
        @foreach($tabs as $key => $tab)
            <li class="nav-item">
                <a class="nav-link {{ $active === $key ? 'active' : '' }}"
                   href="{{ route($tab['route'], $shop->slug) }}">{{ $tab['label'] }}</a>
            </li>
        @endforeach
        @foreach($policyLinks as $link)
            <li class="nav-item">
                <a class="nav-link {{ $active === $link['slug'] ? 'active' : '' }}"
                   href="{{ route('page.show', $link['slug']) }}">{{ $link['label'] }}</a>
            </li>
        @endforeach
    </ul>
</div>
