@php
    $sectionStyle = 'font-size:.68rem;text-transform:uppercase;letter-spacing:.09em;color:#8b95a5;padding:.9rem 1rem .35rem;';
    $tabs = \App\Support\Access\AdminTabRegistry::class;
@endphp
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
        @if($tabs::canSeeAny(['dashboard', 'command-center', 'reports']))
        <li class="nav-section-header" style="{{ $sectionStyle }}">Overview</li>
        @endif
        @if($tabs::canSee('dashboard'))
        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'admin.dashboard' ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="fas fa-home me-2"></i> <span>{{ __('cms.sidebar.dashboard') }}</span></a>
        </li>
        @endif
        @if ($tabs::canSee('command-center'))
        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() === 'admin.command-center.index' ? 'active' : '' }}" href="{{ route('admin.command-center.index') }}">
                <i class="fas fa-gauge-high me-2"></i> <span>Command Center</span>
            </a>
        </li>
        @endif
        @if (auth()->user()?->canAny(['analytics.view', 'dashboard.view', 'intelligence.view', 'orders.view', 'support.view', 'vendors.view', 'customers.view']))
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.command-center.') && Route::currentRouteName() !== 'admin.command-center.index' ? 'active' : '' }}" href="{{ route('admin.command-center.intelligence') }}">
                <i class="fas fa-chart-pie me-2"></i> <span>Intelligence Command Center</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('reports'))
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.reports') ? 'active' : '' }}"
               href="{{ route('admin.reports.index') }}">
                <i class="fas fa-chart-line me-2"></i> <span>Sales Reports</span>
            </a>
        </li>
        @endif

        @if($tabs::canSeeAny(['products', 'categories', 'brands', 'attributes']))
        <li class="nav-section-header" style="{{ $sectionStyle }}">Catalog</li>
        @endif
        @if($tabs::canSee('products'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#productMenu" role="button" aria-expanded="false" aria-controls="productMenu">
                <span><i class="fas fa-box me-2"></i> <span>{{ __('cms.sidebar.products.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ in_array(Route::currentRouteName(), ['admin.products.create', 'admin.products.index', 'admin.products.approval.index']) ? 'show' : '' }}" id="productMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('products'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.products.create' ? 'active' : '' }}" href="{{ route('admin.products.create') }}">{{ __('cms.sidebar.products.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.products.index' ? 'active' : '' }}" href="{{ route('admin.products.index') }}">{{ __('cms.sidebar.products.list') }}</a></li>
                    @can('products.approve')
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.products.approval.index' ? 'active' : '' }}" href="{{ route('admin.products.approval.index') }}">Approvals</a></li>
                    @endcan
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('categories'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#categoryMenu" role="button" aria-expanded="false" aria-controls="categoryMenu">
                <span><i class="fas fa-th-large me-2"></i> <span>{{ __('cms.sidebar.categories.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'admin.categories.create' || Route::currentRouteName() == 'admin.categories.index' ? 'show' : '' }}" id="categoryMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('categories'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.categories.create' ? 'active' : '' }}" href="{{ route('admin.categories.create') }}">{{ __('cms.sidebar.categories.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.categories.index' ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">{{ __('cms.sidebar.categories.list') }}</a></li>
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('brands'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#brandMenu" role="button" aria-expanded="false" aria-controls="brandMenu">
                <span><i class="fas fa-award me-2"></i> <span>{{ __('cms.sidebar.brands.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'admin.brands.create' || Route::currentRouteName() == 'admin.brands.index' ? 'show' : '' }}" id="brandMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('brands'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.brands.create' ? 'active' : '' }}" href="{{ route('admin.brands.create') }}">{{ __('cms.sidebar.brands.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.brands.index' ? 'active' : '' }}" href="{{ route('admin.brands.index') }}">{{ __('cms.sidebar.brands.list') }}</a></li>
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('attributes'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#attributeMenu" role="button" aria-expanded="false" aria-controls="attributeMenu">
                <span><i class="fas fa-cogs me-2"></i> <span>{{ __('cms.sidebar.attributes.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'admin.attributes.create' || Route::currentRouteName() == 'admin.attributes.index' ? 'show' : '' }}" id="attributeMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('attributes'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.attributes.create' ? 'active' : '' }}" href="{{ route('admin.attributes.create') }}">{{ __('cms.sidebar.attributes.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.attributes.index' ? 'active' : '' }}" href="{{ route('admin.attributes.index') }}">{{ __('cms.sidebar.attributes.list') }}</a></li>
                </ul>
            </div>
        </li>
        @endif

        @if($tabs::canSeeAny(['customers', 'vendors']))
        <li class="nav-section-header" style="{{ $sectionStyle }}">Customers &amp; Vendors</li>
        @endif
        @if($tabs::canSee('customers'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}">
                <i class="fas fa-user-friends me-2"></i> <span>{{ __('cms.sidebar.customers.title') }}</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('vendors'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#vendorMenu" role="button" aria-expanded="false" aria-controls="vendorMenu">
                <span><i class="fas fa-user-tag me-2"></i> <span>{{ __('cms.sidebar.vendors.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ in_array(Route::currentRouteName(), ['admin.vendors.create', 'admin.vendors.index', 'admin.vendors.verification']) ? 'show' : '' }}" id="vendorMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('vendors'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.vendors.create' ? 'active' : '' }}" href="{{ route('admin.vendors.create') }}">{{ __('cms.sidebar.vendors.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.vendors.index' ? 'active' : '' }}" href="{{ route('admin.vendors.index') }}">{{ __('cms.sidebar.vendors.list') }}</a></li>
                    @can('vendors.approve')
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.vendors.verification' ? 'active' : '' }}" href="{{ route('admin.vendors.verification') }}">Verification queue</a></li>
                    @endcan
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSeeAny(['orders', 'payments', 'refunds', 'product-reports', 'support', 'sales', 'finance', 'payouts']))
        <li class="nav-section-header" style="{{ $sectionStyle }}">Sales &amp; Operations</li>
        @endif
        @if($tabs::canSee('orders'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}">
                <i class="fas fa-shopping-cart me-2"></i> <span>{{ __('cms.sidebar.orders.title') }}</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('payments'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}">
                <i class="fas fa-credit-card me-2"></i> <span>{{ __('cms.sidebar.payments.title') }}</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('refunds'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.refunds.*') ? 'active' : '' }}" href="{{ route('admin.refunds.index') }}">
                <i class="fas fa-rotate-left me-2"></i> <span>{{ __('cms.sidebar.refunds.title') }}</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('product-reports') && Route::has('admin.product-reports.index'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.product-reports.*') ? 'active' : '' }}" href="{{ route('admin.product-reports.index') }}">
                <i class="fas fa-flag me-2"></i> Product Reports
            </a>
        </li>
        @endif
        @if ($tabs::canSee('support'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#supportMenu" role="button" aria-expanded="false" aria-controls="supportMenu">
                <span><i class="fas fa-headset me-2"></i> <span>Support Tickets</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::is('admin.support-tickets.*', 'admin.support.canned.*', 'admin.support.sla.*') ? 'show' : '' }}" id="supportMenu">
                <ul class="nav flex-column ms-3">
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.support-tickets.index' ? 'active' : '' }}" href="{{ route('admin.support-tickets.index') }}">Inbox</a></li>
                    @if (auth()->user()?->can('support.manage_canned') && feature_enabled('support_canned_responses'))
                    <li><a class="nav-link {{ Route::is('admin.support.canned.*') ? 'active' : '' }}" href="{{ route('admin.support.canned.index') }}">Canned responses</a></li>
                    @endif
                    @if (auth()->user()?->can('support.manage_sla') && feature_enabled('support_sla'))
                    <li><a class="nav-link {{ Route::is('admin.support.sla.*') ? 'active' : '' }}" href="{{ route('admin.support.sla.index') }}">SLA rules</a></li>
                    @endif
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('finance'))
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.finance') ? 'active' : '' }}"
               href="{{ route('admin.finance.index') }}">
                <i class="fas fa-sack-dollar me-2"></i> <span>Finance Command Center</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('payouts'))
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.payouts') ? 'active' : '' }}"
               href="{{ route('admin.payouts.index') }}">
                <i class="fas fa-money-bill-transfer me-2"></i> <span>Vendor Payouts</span>
            </a>
        </li>
        @endif

        @if($tabs::canSeeAny(['ad-manager', 'homepage', 'banners', 'product-reviews', 'pricing-marketing', 'pages', 'menus', 'menu-items', 'social-media-links']))
        <li class="nav-section-header" style="{{ $sectionStyle }}">Marketing &amp; Content</li>
        @endif
        @if($tabs::canSee('ad-manager'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#adManagerMenu" role="button" aria-expanded="{{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.') ? 'true' : 'false' }}" aria-controls="adManagerMenu">
                <span><i class="fas fa-bullhorn me-2"></i> <span>Ad Manager</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.') ? 'show' : '' }}" id="adManagerMenu">
                <ul class="nav flex-column ms-3">
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.ad-manager.overview' ? 'active' : '' }}" href="{{ route('admin.ad-manager.overview') }}">Overview</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.submissions') ? 'active' : '' }}" href="{{ route('admin.ad-manager.submissions.index') }}">Submissions</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.campaigns') ? 'active' : '' }}" href="{{ route('admin.ad-manager.campaigns.index') }}">Campaigns</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.placements') ? 'active' : '' }}" href="{{ route('admin.ad-manager.placements.index') }}">Placements</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.packages') ? 'active' : '' }}" href="{{ route('admin.ad-manager.packages.index') }}">Packages</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.payments') ? 'active' : '' }}" href="{{ route('admin.ad-manager.payments.index') }}">Payments</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.analytics') ? 'active' : '' }}" href="{{ route('admin.ad-manager.analytics.index') }}">Analytics</a></li>
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.ad-manager.finances' ? 'active' : '' }}" href="{{ route('admin.ad-manager.finances') }}">Finances &amp; Ledger</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.auctions') ? 'active' : '' }}" href="{{ route('admin.ad-manager.auctions') }}">Auction Decisions</a></li>
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.ad-manager.policies' ? 'active' : '' }}" href="{{ route('admin.ad-manager.policies') }}">Policy Reviews</a></li>
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.ad-manager.optimization' ? 'active' : '' }}" href="{{ route('admin.ad-manager.optimization') }}">Optimisation</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.vendors') ? 'active' : '' }}" href="{{ route('admin.ad-manager.vendors.index') }}">Vendors</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.settings') ? 'active' : '' }}" href="{{ route('admin.ad-manager.settings.index') }}">Settings</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.ad-manager.audit') ? 'active' : '' }}" href="{{ route('admin.ad-manager.audit.index') }}">Audit Log</a></li>
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('homepage'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center {{ str_starts_with((string) Route::currentRouteName(), 'admin.homepage.') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#homepageMenu" role="button" aria-expanded="{{ str_starts_with((string) Route::currentRouteName(), 'admin.homepage.') ? 'true' : 'false' }}" aria-controls="homepageMenu">
                <span><i class="fas fa-house me-2"></i> <span>Homepage Builder</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ str_starts_with((string) Route::currentRouteName(), 'admin.homepage.') ? 'show' : '' }}" id="homepageMenu">
                <ul class="nav flex-column ms-3">
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.homepage.index' ? 'active' : '' }}" href="{{ route('admin.homepage.index') }}">Overview</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.homepage.slides') ? 'active' : '' }}" href="{{ route('admin.homepage.slides.index') }}">Hero Slides</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.homepage.tiles') ? 'active' : '' }}" href="{{ route('admin.homepage.tiles.index') }}">Collection Tiles</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.homepage.sections') ? 'active' : '' }}" href="{{ route('admin.homepage.sections.index') }}">Sections</a></li>
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.homepage.sidebar' ? 'active' : '' }}" href="{{ route('admin.homepage.sidebar') }}">Marketplace Sidebar</a></li>
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.homepage.footer' ? 'active' : '' }}" href="{{ route('admin.homepage.footer') }}">Footer</a></li>
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.homepage.settings' ? 'active' : '' }}" href="{{ route('admin.homepage.settings') }}">Settings</a></li>
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.homepage.preview' ? 'active' : '' }}" href="{{ route('admin.homepage.preview') }}">Preview</a></li>
                </ul>
            </div>
        </li>
        @endif
        @if(Route::has('admin.storefront.index') && $tabs::canSee('pages'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.storefront.*') ? 'active' : '' }}" href="{{ route('admin.storefront.index') }}">
                <i class="fas fa-layer-group me-2"></i> <span>Storefront Builder</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('banners'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center {{ str_starts_with((string) Route::currentRouteName(), 'admin.banners') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#bannerMenu" role="button" aria-expanded="{{ str_starts_with((string) Route::currentRouteName(), 'admin.banners') ? 'true' : 'false' }}" aria-controls="bannerMenu">
                <span><i class="fas fa-image me-2"></i> <span>{{ __('cms.sidebar.banners.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'admin.banners.create' || Route::currentRouteName() == 'admin.banners.index' ? 'show' : '' }}" id="bannerMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('banners'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.banners.create' ? 'active' : '' }}" href="{{ route('admin.banners.create') }}">{{ __('cms.sidebar.banners.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.banners.index' ? 'active' : '' }}" href="{{ route('admin.banners.index') }}">{{ __('cms.sidebar.banners.list') }}</a></li>
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('product-reviews'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}" href="{{ route('admin.reviews.index') }}">
                <i class="fas fa-star me-2"></i> <span>{{ __('cms.sidebar.product_reviews.title') }}</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('pricing-marketing'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center {{ str_starts_with((string) Route::currentRouteName(), 'admin.coupons') || str_starts_with((string) Route::currentRouteName(), 'admin.tax-rates') || str_starts_with((string) Route::currentRouteName(), 'admin.shipping-rates') || str_starts_with((string) Route::currentRouteName(), 'admin.newsletter') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#marketingMenu" role="button" aria-expanded="{{ str_starts_with((string) Route::currentRouteName(), 'admin.coupons') || str_starts_with((string) Route::currentRouteName(), 'admin.tax-rates') || str_starts_with((string) Route::currentRouteName(), 'admin.shipping-rates') || str_starts_with((string) Route::currentRouteName(), 'admin.newsletter') ? 'true' : 'false' }}" aria-controls="marketingMenu">
                <span><i class="fas fa-tags me-2"></i> <span>Pricing &amp; Marketing</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ str_starts_with((string) Route::currentRouteName(), 'admin.coupons') || str_starts_with((string) Route::currentRouteName(), 'admin.tax-rates') || str_starts_with((string) Route::currentRouteName(), 'admin.shipping-rates') || str_starts_with((string) Route::currentRouteName(), 'admin.newsletter') ? 'show' : '' }}" id="marketingMenu">
                <ul class="nav flex-column ms-3">
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.coupons') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}">Coupons</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.tax-rates') ? 'active' : '' }}" href="{{ route('admin.tax-rates.index') }}">Tax Rates</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.shipping-rates') ? 'active' : '' }}" href="{{ route('admin.shipping-rates.index') }}">Shipping Rates</a></li>
                    <li><a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.newsletter') ? 'active' : '' }}" href="{{ route('admin.newsletter.index') }}">Newsletter</a></li>
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('pages'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center {{ str_starts_with((string) Route::currentRouteName(), 'admin.pages.') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#pageMenu" role="button" aria-expanded="{{ str_starts_with((string) Route::currentRouteName(), 'admin.pages.') ? 'true' : 'false' }}" aria-controls="pageMenu">
                <span><i class="fas fa-file-alt me-2"></i> <span>{{ __('cms.sidebar.pages.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ str_starts_with((string) Route::currentRouteName(), 'admin.pages.') ? 'show' : '' }}" id="pageMenu">
                <ul class="nav flex-column ms-3">
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.pages.index' ? 'active' : '' }}" href="{{ route('admin.pages.index') }}">All Pages</a></li>
                    @if($tabs::canWrite('pages'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.pages.create' ? 'active' : '' }}" href="{{ route('admin.pages.create') }}">{{ __('cms.sidebar.pages.add_new') }}</a></li>
                    @endif
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('menus'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuMenu" role="button" aria-expanded="false" aria-controls="menuMenu">
                <span><i class="fas fa-bars me-2"></i> <span>{{ __('cms.sidebar.menu.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'admin.menus.create' || Route::currentRouteName() == 'admin.menus.index' ? 'show' : '' }}" id="menuMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('menus'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.menus.create' ? 'active' : '' }}" href="{{ route('admin.menus.create') }}">{{ __('cms.sidebar.menu.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.menus.index' ? 'active' : '' }}" href="{{ route('admin.menus.index') }}">{{ __('cms.sidebar.menu.list') }}</a></li>
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('menu-items'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuItemMenu" role="button" aria-expanded="false" aria-controls="menuItemMenu">
                <span><i class="fas fa-list me-2"></i> <span>{{ __('cms.sidebar.menu_items.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'admin.menuitems.create' || Route::currentRouteName() == 'admin.menuitems.index' ? 'show' : '' }}" id="menuItemMenu">
                <ul class="nav flex-column ms-3">
                    @if(isset($menu) && $menu)
                    @if($tabs::canWrite('menu-items'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.menu.items.create' ? 'active' : '' }}" href="{{ route('admin.menus.items.create', $menu) }}">{{ __('cms.sidebar.menu_items.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.menus.item.index' ? 'active' : '' }}" href="{{ route('admin.menus.item.index') }}">{{ __('cms.sidebar.menu_items.list') }}</a></li>
                    @endif
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('social-media-links'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#socialMediaLinkMenu" role="button" aria-expanded="false" aria-controls="socialMediaLinkMenu">
                <span><i class="fas fa-link me-2"></i> <span>{{ __('cms.sidebar.social_media_links.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'admin.social-media-links.create' || Route::currentRouteName() == 'admin.social-media-links.index' ? 'show' : '' }}" id="socialMediaLinkMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('social-media-links'))
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.social-media-links.create' ? 'active' : '' }}" href="{{ route('admin.social-media-links.create') }}">{{ __('cms.sidebar.social_media_links.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.social-media-links.index' ? 'active' : '' }}" href="{{ route('admin.social-media-links.index') }}">{{ __('cms.sidebar.social_media_links.list') }}</a></li>
                </ul>
            </div>
        </li>
        @endif

        @if($tabs::canSeeAny(['algorithms', 'automation', 'intelligence', 'search', 'pricing']))
        <li class="nav-section-header" style="{{ $sectionStyle }}">Intelligence</li>
        @endif
        @can('algorithms.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.algorithms') ? 'active' : '' }}"
               href="{{ route('admin.algorithms.index') }}">
                <i class="fas fa-microchip me-2"></i> <span>Algorithm Engine</span>
            </a>
        </li>
        @endcan
        @can('automation.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.automation') ? 'active' : '' }}"
               href="{{ route('admin.automation.index') }}">
                <i class="fas fa-robot me-2"></i> <span>Automation Engine</span>
            </a>
        </li>
        @endcan
        @can('intelligence.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.intelligence') ? 'active' : '' }}"
               href="{{ route('admin.intelligence.index') }}">
                <i class="fas fa-chart-pie me-2"></i> <span>Marketplace Intelligence</span>
            </a>
        </li>
        @endcan
        @can('search.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.search') ? 'active' : '' }}"
               href="{{ route('admin.search.index') }}">
                <i class="fas fa-magnifying-glass-chart me-2"></i> <span>Search Engine</span>
            </a>
        </li>
        @endcan
        @can('pricing.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.pricing') ? 'active' : '' }}"
               href="{{ route('admin.pricing.index') }}">
                <i class="fas fa-calculator me-2"></i> <span>Pricing &amp; Promotions</span>
            </a>
        </li>
        @endcan

        @if($tabs::canSeeAny(['staff', 'hr', 'roles', 'approvals', 'audit-logs', 'features', 'integrity']))
        <li class="nav-section-header" style="{{ $sectionStyle }}">Governance</li>
        @endif
        @can('staff.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.staff') ? 'active' : '' }}"
               href="{{ route('admin.staff.index') }}">
                <i class="fas fa-user-shield me-2"></i> <span>Staff Management</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.logistics-staff') ? 'active' : '' }}"
               href="{{ route('admin.logistics-staff.index') }}">
                <i class="fas fa-truck me-2"></i> <span>Logistics Staff</span>
            </a>
        </li>
        @endcan
        @can('roles.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.roles') ? 'active' : '' }}"
               href="{{ route('admin.roles.index') }}">
                <i class="fas fa-shield-halved me-2"></i> <span>Roles &amp; Permissions</span>
            </a>
        </li>
        @endcan
        @can('approvals.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.approvals') ? 'active' : '' }}"
               href="{{ route('admin.approvals.index') }}">
                <i class="fas fa-clipboard-check me-2"></i> <span>Approvals</span>
            </a>
        </li>
        @endcan
        @can('audit.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.audit-logs') ? 'active' : '' }}"
               href="{{ route('admin.audit-logs.index') }}">
                <i class="fas fa-clipboard-list me-2"></i> <span>Audit Logs</span>
            </a>
        </li>
        @endcan
        @can('features.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.features') ? 'active' : '' }}"
               href="{{ route('admin.features.index') }}">
                <i class="fas fa-toggle-on me-2"></i> <span>Features</span>
            </a>
        </li>
        @endcan

        @if ($tabs::canSee('integrity'))
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.integrity') ? 'active' : '' }}"
               href="{{ route('admin.integrity.index') }}">
                <i class="fas fa-scale-balanced me-2"></i> <span>Integrity</span>
            </a>
        </li>
        @endif

        @if($tabs::canSeeAny(['site-settings', 'admin-settings', 'terminal', 'currencies', 'payment-gateways', 'integrations', 'workflows', 'business-rules', 'regions']))
        <li class="nav-section-header" style="{{ $sectionStyle }}">System</li>
        @endif
        @if($tabs::canSee('site-settings'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center {{ str_starts_with((string) Route::currentRouteName(), 'site-settings') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#siteSettingsMenu" role="button" aria-expanded="{{ Route::currentRouteName() == 'site-settings.index' ? 'true' : 'false' }}" aria-controls="siteSettingsMenu">
                <span><i class="fas fa-cog me-2"></i> <span>{{ __('cms.sidebar.site_settings.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ Route::currentRouteName() == 'site-settings.index' ? 'show' : '' }}" id="siteSettingsMenu">
                <ul class="nav flex-column ms-3">
                    <li><a class="nav-link {{ Route::currentRouteName() == 'site-settings.index' ? 'active' : '' }}" href="{{ route('site-settings.index') }}">{{ __('cms.sidebar.site_settings.manage') }}</a></li>
                </ul>
            </div>
        </li>
        @endif
        @can('admin_settings.view')
        <li class="nav-item">
            <a class="nav-link {{ str_starts_with((string) Route::currentRouteName(), 'admin.admin-settings') ? 'active' : '' }}"
               href="{{ route('admin.admin-settings.index') }}">
                <i class="fas fa-sliders me-2"></i> <span>Admin Settings</span>
            </a>
        </li>
        @endcan
        @if($tabs::canSee('currencies'))
        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'admin.currencies.index' ? 'active' : '' }}"
               href="{{ route('admin.currencies.index') }}">
                <i class="fas fa-dollar-sign me-2"></i> <span>Currencies</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('payment-gateways'))
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center {{ str_starts_with((string) Route::currentRouteName(), 'admin.payment-gateways') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#gatewaysMenu" role="button" aria-expanded="{{ str_starts_with((string) Route::currentRouteName(), 'admin.payment-gateways') ? 'true' : 'false' }}" aria-controls="gatewaysMenu">
                <span><i class="fas fa-money-check-dollar me-2"></i> <span>{{ __('cms.sidebar.payment_gateways.title') }}</span></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse {{ in_array(Route::currentRouteName(), ['admin.payment-gateways.index', 'admin.payment-gateways.getData', 'admin.payment-gateways.edit', 'admin.payment-gateways.create', 'admin.payment-gateways.store']) ? 'show' : '' }}" id="gatewaysMenu">
                <ul class="nav flex-column ms-3">
                    @if($tabs::canWrite('payment-gateways'))
                    <li><a class="nav-link {{ in_array(Route::currentRouteName(), ['admin.payment-gateways.create', 'admin.payment-gateways.store']) ? 'active' : '' }}" href="{{ route('admin.payment-gateways.create') }}">{{ __('cms.sidebar.payment_gateways.add_new') }}</a></li>
                    @endif
                    <li><a class="nav-link {{ Route::currentRouteName() == 'admin.payment-gateways.index' ? 'active' : '' }}" href="{{ route('admin.payment-gateways.index') }}">{{ __('cms.sidebar.payment_gateways.list') }}</a></li>
                </ul>
            </div>
        </li>
        @endif
        @if($tabs::canSee('integrations'))
        <li class="nav-item">
            <a class="nav-link {{ in_array(Route::currentRouteName(), ['admin.integrations.index', 'admin.integrations.create', 'admin.integrations.configure']) ? 'active' : '' }}"
               href="{{ route('admin.integrations.index') }}">
                <i class="fas fa-plug me-2"></i> <span>Integrations</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('workflows'))
        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'admin.workflows.index' ? 'active' : '' }}"
               href="{{ route('admin.workflows.index') }}">
                <i class="fas fa-diagram-project me-2"></i> <span>Workflows</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('business-rules'))
        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'admin.business-rules.index' ? 'active' : '' }}"
               href="{{ route('admin.business-rules.index') }}">
                <i class="fas fa-list-check me-2"></i> <span>Business Rules</span>
            </a>
        </li>
        @endif
        @if($tabs::canSee('regions') && Route::has('admin.regions.index'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.regions.*') ? 'active' : '' }}" href="{{ route('admin.regions.index') }}">
                <i class="fas fa-map-marker-alt me-2"></i> Regions &amp; Locations
            </a>
        </li>
        @endif
        @can('settings.view')
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.docs.*') ? 'active' : '' }}" href="{{ route('admin.docs.index') }}">
                <i class="fas fa-book-open me-2"></i> Platform Documentation
            </a>
        </li>
        @endcan
        @php($customTabs = app(\App\Services\Admin\AdminSettingsService::class)->visibleCustomTabs(auth()->user()))
        @if (! empty($customTabs))
            <li class="nav-section-header" style="{{ $sectionStyle }}">Custom</li>
            @foreach ($customTabs as $customTab)
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="{{ \Illuminate\Support\Str::startsWith($customTab->url, ['http://', 'https://']) ? $customTab->url : url($customTab->url) }}">
                        <i class="{{ $customTab->icon ?: 'fas fa-link' }} me-2"></i> <span>{{ $customTab->label }}</span>
                        @if ($customTab->group)<small class="ms-auto text-muted">{{ $customTab->group }}</small>@endif
                    </a>
                </li>
            @endforeach
        @endif
    </ul>
</nav>
