<!-- Sidebar -->
<nav id="sidebar" class="d-flex flex-column p-3">
    <div class="logo-container">
        <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" style="height:40px;"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
        <span style="display:none; font-size:26px; font-weight:800; color:#f68b1e;">Juvo</span>
    </div>
    <div class="search-container position-relative">
        <input type="text" class="form-control" placeholder="{{ __('cms.sidebar.search_placeholder') }}" id="searchInput" autocomplete="off">
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'vendor.dashboard' ? 'active' : '' }}" href="{{ route('vendor.dashboard') }}"><i class="fas fa-home me-2"></i> <span>{{ __('cms.sidebar.dashboard') }}</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'vendor.analytics') ? 'active' : '' }}" href="{{ route('vendor.analytics.index') }}">
                <i class="fas fa-chart-line me-2"></i> <span>Analytics</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'vendor.store.') ? 'active' : '' }}" href="{{ route('vendor.store.edit') }}">
                <i class="fas fa-store me-2"></i> <span>My Store</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'vendor.support') ? 'active' : '' }}" href="{{ route('vendor.support.index') }}">
                <i class="fas fa-headset me-2"></i> <span>Support &amp; Reviews</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#productMenu" role="button" aria-expanded="false" aria-controls="productMenu">
                <span><i class="fas fa-box me-2"></i> <span>{{ __('cms.sidebar.products.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'vendor.products.create' || Route::currentRouteName() == 'vendor.products.index' ? 'show' : '' }}" id="productMenu">
                <ul class="nav flex-column ms-3">
                    <li><a class="nav-link {{ Route::currentRouteName() == 'vendor.products.create' ? 'active' : '' }}" href="{{ route('vendor.products.create') }}">{{ __('cms.sidebar.products.add_new') }}</a></li>
                    <li><a class="nav-link {{ Route::currentRouteName() == 'vendor.products.index' ? 'active' : '' }}" href="{{ route('vendor.products.index') }}">{{ __('cms.sidebar.products.list') }}</a></li>
                </ul>
            </div>
        </li> 
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#orderMenu" role="button" aria-expanded="false" aria-controls="orderMenu">
                <span><i class="fas fa-shopping-cart me-2"></i> <span>{{ __('cms.sidebar.orders.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::is('vendor.orders.*') ? 'show' : '' }}" id="orderMenu">
                <ul class="nav flex-column ms-3">
                    <li>
                        <a class="nav-link {{ Route::currentRouteName() == 'vendor.orders.index' ? 'active' : '' }}" href="{{ route('vendor.orders.index') }}">
                           {{ __('cms.sidebar.orders.list') }}
                        </a>
                    </li>
                </ul>
            </div>
        </li>
          <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#vendorProductReviewMenu" role="button" aria-expanded="false" aria-controls="vendorProductReviewMenu">
                <span><i class="fas fa-star me-2"></i> <span>{{ __('cms.sidebar.product_reviews.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ in_array(Route::currentRouteName(), ['vendor.reviews.index']) ? 'show' : '' }}" id="vendorProductReviewMenu">
                <ul class="nav flex-column ms-3">
                    <li>
                        <a class="nav-link {{ Route::currentRouteName() == 'vendor.reviews.index' ? 'active' : '' }}" href="{{ route('vendor.reviews.index') }}">
                            {{ __('cms.sidebar.product_reviews.list') }}
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'vendor.finance') ? 'active' : '' }}" href="{{ route('vendor.finance.index') }}">
                <i class="fas fa-coins me-2"></i> <span>Finance</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#vendorMarketingMenu" role="button" aria-expanded="false" aria-controls="vendorMarketingMenu">
                <span><i class="fas fa-bullhorn me-2"></i> <span>{{ __('cms.sidebar.marketing.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::is('vendor.ads.*', 'vendor.promotions.*', 'vendor.coupons.*') ? 'show' : '' }}" id="vendorMarketingMenu">
                <ul class="nav flex-column ms-3">
                    <li>
                        <a class="nav-link {{ Route::currentRouteName() == 'vendor.ads.index' ? 'active' : '' }}" href="{{ route('vendor.ads.index') }}">
                            <i class="fas fa-flag me-1"></i> {{ __('cms.sidebar.ads.title') }}
                        </a>
                    </li>
                    <li>
                        <a class="nav-link {{ Route::currentRouteName() == 'vendor.promotions.index' ? 'active' : '' }}" href="{{ route('vendor.promotions.index') }}">
                            <i class="fas fa-tags me-1"></i> {{ __('cms.sidebar.promotions.title') }}
                        </a>
                    </li>
                    <li>
                        <a class="nav-link {{ Route::is('vendor.coupons.*') ? 'active' : '' }}" href="{{ route('vendor.coupons.index') }}">
                            <i class="fas fa-ticket me-1"></i> Coupons
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#vendorSettingsMenu" role="button" aria-expanded="false" aria-controls="vendorSettingsMenu">
                <span><i class="fas fa-cog me-2"></i> <span>{{ __('cms.sidebar.settings.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::is('vendor.settings.*', 'vendor.staff.*') ? 'show' : '' }}" id="vendorSettingsMenu">
                <ul class="nav flex-column ms-3">
                    <li>
                        <a class="nav-link {{ Route::currentRouteName() == 'vendor.settings.index' ? 'active' : '' }}" href="{{ route('vendor.settings.index') }}">
                            <i class="fas fa-store me-1"></i> {{ __('cms.sidebar.settings.store') }}
                        </a>
                    </li>
                    <li>
                        <a class="nav-link {{ Route::is('vendor.staff.*') ? 'active' : '' }}" href="{{ route('vendor.staff.index') }}">
                            <i class="fas fa-users-cog me-1"></i> Staff
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="{{ route('vendor.profile.edit') }}">
                            <i class="fas fa-user me-1"></i> {{ __('cms.sidebar.site_settings.manage') }}
                        </a>
                    </li>
                </ul>
            </div>
        </li>
    </ul>
</nav>