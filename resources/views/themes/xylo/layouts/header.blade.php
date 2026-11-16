@php
    $wishlistCount = 0;
    $notificationCount = 0;
    if (auth('customer')->check()) {
        $customer = auth('customer')->user();
        $wishlistCount = $customer->wishlistProducts()->count();
        $notificationCount = $customer->notifications()->whereNull('read_at')->count();
    }
    $cartCount = session('cart') ? collect(session('cart'))->sum('quantity') : 0;
    $jvCatIcons = [
        'phones-tablets' => 'fa-solid fa-mobile-screen-button',
        'electronics' => 'fa-solid fa-tv',
        'computing' => 'fa-solid fa-laptop',
        'home-office' => 'fa-solid fa-couch',
        'appliances' => 'fa-solid fa-blender',
        'fashion' => 'fa-solid fa-shirt',
        'health-beauty' => 'fa-solid fa-spray-can-sparkles',
        'groceries' => 'fa-solid fa-basket-shopping',
        'supermarket' => 'fa-solid fa-cart-shopping',
        'baby' => 'fa-solid fa-baby',
        'sporting' => 'fa-solid fa-dumbbell',
        'gaming' => 'fa-solid fa-gamepad',
        'books' => 'fa-solid fa-book',
        'automotive' => 'fa-solid fa-car',
        'pets' => 'fa-solid fa-paw',
        'toys' => 'fa-solid fa-shapes',
        'music' => 'fa-solid fa-headphones',
        'kitchen' => 'fa-solid fa-utensils',
        'hardware' => 'fa-solid fa-screwdriver-wrench',
        'garden' => 'fa-solid fa-seedling',
        'bags' => 'fa-solid fa-bag-shopping',
        'shoes' => 'fa-solid fa-shoe-prints',
        'jewelry' => 'fa-solid fa-gem',
        'office' => 'fa-solid fa-briefcase',
        'school' => 'fa-solid fa-graduation-cap',
        'agriculture' => 'fa-solid fa-tractor',
        'pharmacy' => 'fa-solid fa-prescription-bottle-medical',
        'wines-spirits' => 'fa-solid fa-wine-glass',
        'air-conditioners' => 'fa-solid fa-snowflake',
        'cookers' => 'fa-solid fa-fire-burner',
        'refrigerators' => 'fa-solid fa-snowflake',
        'washing-machines' => 'fa-solid fa-soap',
        'diapers' => 'fa-solid fa-baby-carriage',
        'computer-accessories' => 'fa-solid fa-computer-mouse',
        'desktops' => 'fa-solid fa-desktop',
        'laptops' => 'fa-solid fa-laptop',
        'monitors' => 'fa-solid fa-display',
        'printers' => 'fa-solid fa-print',
        'audio-music' => 'fa-solid fa-music',
        'cameras' => 'fa-solid fa-camera',
        'tvs-video' => 'fa-solid fa-tv',
        'mens-fashion' => 'fa-solid fa-person',
        't-shirts' => 'fa-solid fa-shirt',
        'womens-fashion' => 'fa-solid fa-person-dress',
        'beverages' => 'fa-solid fa-mug-hot',
        'snacks' => 'fa-solid fa-cookie-bite',
        'fragrances' => 'fa-solid fa-spray-can',
        'makeup' => 'fa-solid fa-brush',
        'skincare' => 'fa-solid fa-droplet',
        'bedding' => 'fa-solid fa-bed',
        'furniture' => 'fa-solid fa-couch',
        'home-decor' => 'fa-solid fa-lightbulb',
        'kitchen-dining' => 'fa-solid fa-utensils',
        'phone-accessories' => 'fa-solid fa-plug',
        'smartphones' => 'fa-solid fa-mobile-screen-button',
        'tablets' => 'fa-solid fa-tablet-screen-button',
        'fitness' => 'fa-solid fa-dumbbell',
        'sport-shoes' => 'fa-solid fa-shoe-prints',
    ];
@endphp

<header class="jv-header">
    @php
        $jvNotice = \App\Models\StoreSetting::where('key', 'announcement_text')->value('value');
    @endphp
    @if(!empty($jvNotice))
        <div class="jv-notice">{{ $jvNotice }}</div>
    @endif
    {{-- Top strip --}}
    <div class="jv-topstrip">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-none d-md-flex align-items-center">
                <a href="{{ route('join.sell') }}"><i class="fa-solid fa-store me-1"></i> Sell on Juvo</a>
                <span class="jv-divider mx-1">|</span>
                <a href="{{ route('join.deliver') }}"><i class="fa-solid fa-motorcycle me-1"></i> Deliver with Juvo</a>
                <span class="jv-divider mx-1">|</span>
                <a href="{{ route('help.index') }}">Help</a>
                <span class="jv-divider mx-1">|</span>
                <a href="{{ route('shop.index') }}">Shop</a>
                <span class="jv-divider mx-1">|</span>
                <a href="{{ route('company') }}">Our Company</a>
            </div>
            <div class="d-flex align-items-center ms-auto">
                <a href="{{ auth('customer')->check() ? route('customer.profile.edit') : route('customer.login') }}">
                    <i class="fa-regular fa-user me-1"></i>
                    {{ auth('customer')->check() ? auth('customer')->user()->name : 'Sign in' }}
                </a>
                <span class="jv-divider mx-1">|</span>
                <a href="{{ route('customer.register') }}">Sign up</a>
            </div>
        </div>
    </div>

    {{-- Main row --}}
    <div class="container">
        <div class="jv-header-row">
            @include('themes.xylo.partials.logo')

            <div class="jv-search">
                <form action="{{ url('/search') }}" method="GET" autocomplete="off">
                    <input type="text" id="jv-search-input" name="q"
                           placeholder="Search products, brands and categories"
                           value="{{ request('q') }}">
                    <button type="submit" aria-label="Search"><i class="fa fa-search"></i></button>
                </form>
                <div id="jv-search-suggest" class="jv-suggest d-none"></div>
            </div>

            <div class="jv-actions">
                {{-- Notifications --}}
                <div class="dropdown">
                    <a href="#" class="jv-action notification-bell" id="notificationBell" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        <span class="jv-label d-none d-md-inline">Notifications</span>
                        @if($notificationCount > 0)
                            <span class="jv-count notification-count" id="notification-count">{{ $notificationCount }}</span>
                        @endif
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-0 notification-dropdown" style="width: 380px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <h6 class="mb-0">Notifications</h6>
                            @if($notificationCount > 0)
                                <a href="#" class="btn btn-sm btn-link text-decoration-none mark-all-read" style="font-size: 12px;">Mark all read</a>
                            @endif
                        </li>
                        <li class="notification-list">
                            @if(auth('customer')->check())
                                @php $notifications = auth('customer')->user()->notifications()->latest()->limit(10)->get(); @endphp
                                @if($notifications->isEmpty())
                                    <div class="px-3 py-4 text-center text-muted">
                                        <i class="fa-regular fa-bell-slash fa-2x mb-2 d-block"></i>
                                        No notifications yet
                                    </div>
                                @else
                                    @foreach($notifications as $notification)
                                        <a href="{{ $notification->data['url'] ?? '#' }}" class="dropdown-item notification-item {{ $notification->read_at ? 'read' : 'unread' }}" data-id="{{ $notification->id }}">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="notification-icon {{ $notification->type }}">
                                                    @php
                                                        $icons = [
                                                            'order_placed' => 'fa-solid fa-box',
                                                            'order_accepted' => 'fa-solid fa-check-circle',
                                                            'order_processing' => 'fa-solid fa-cogs',
                                                            'order_packed' => 'fa-solid fa-box-open',
                                                            'order_ready_for_pickup' => 'fa-solid fa-store',
                                                            'order_dispatched' => 'fa-solid fa-truck',
                                                            'order_rider_arrived' => 'fa-solid fa-motorcycle',
                                                            'order_delivered' => 'fa-solid fa-check-double',
                                                            'order_cancelled' => 'fa-solid fa-xmark-circle',
                                                            'order_refunded' => 'fa-solid fa-rotate-left',
                                                            'new_message' => 'fa-solid fa-message',
                                                            'review_received' => 'fa-solid fa-star',
                                                            'promotion' => 'fa-solid fa-tag',
                                                            'system' => 'fa-solid fa-info-circle',
                                                        ];
                                                        $icon = $icons[$notification->type] ?? 'fa-solid fa-bell';
                                                    @endphp
                                                    <i class="{{ $icon }}"></i>
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="fw-semibold small">{{ $notification->data['title'] ?? 'Notification' }}</div>
                                                    <div class="small text-muted">{{ $notification->data['message'] ?? '' }}</div>
                                                    <div class="text-xs text-muted mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                                                </div>
                                                @if(!$notification->read_at)
                                                    <span class="badge bg-orange rounded-pill">New</span>
                                                @endif
                                            </div>
                                        </a>
                                    @endforeach
                                    <div class="px-3 py-2 text-center border-top">
                                        <a href="{{ route('notifications.index') }}" class="small">View all notifications</a>
                                    </div>
                                @endif
                            @else
                                <div class="px-3 py-4 text-center text-muted">
                                    <i class="fa-regular fa-bell-slash fa-2x mb-2 d-block"></i>
                                    <a href="{{ route('customer.login') }}" class="btn btn-sm btn-orange mt-2">Sign in to view notifications</a>
                                </div>
                            @endif
                        </li>
                    </ul>
                </div>

                {{-- Account --}}
                <div class="dropdown">
                    <a href="#" class="jv-action" data-bs-toggle="dropdown" aria-expanded="false">
                        @auth('customer')
                            @php $customer = Auth::guard('customer')->user(); @endphp
                            <img class="jv-avatar"
                                 src="{{ $customer->profile_image ? asset('storage/'.$customer->profile_image) : \App\Support\Avatar::initials($customer->name, 'f68b1e', 40) }}"
                                 alt="{{ $customer->name }}">
                            <span class="jv-label">Account</span>
                        @else
                            <i class="fa-regular fa-user"></i>
                            <span class="jv-label">Account</span>
                        @endauth
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-2">
                        @guest('customer')
                            <li><a class="dropdown-item" href="{{ route('customer.login') }}"><i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Sign In</a></li>
                            <li><a class="dropdown-item" href="{{ route('customer.register') }}"><i class="fa-solid fa-user-plus me-2"></i>Sign Up</a></li>
                        @else
                            <li><a class="dropdown-item" href="{{ route('customer.profile.edit') }}"><i class="fa-regular fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('customer.orders.index') }}"><i class="fa-solid fa-box-open me-2"></i>My Orders</a></li>
                            <li><a class="dropdown-item" href="{{ route('customer.wishlist.index') }}"><i class="fa-regular fa-heart me-2"></i>Wishlist</a></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('customer.logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('jv-customer-logout').submit();">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                                </a>
                                <form id="jv-customer-logout" action="{{ route('customer.logout') }}" method="POST" class="d-none">@csrf</form>
                            </li>
                        @endguest
                    </ul>
                </div>

                {{-- Wishlist --}}
                <a href="{{ auth('customer')->check() ? route('customer.wishlist.index') : route('customer.login') }}" class="jv-action">
                    <i class="fa-regular fa-heart"></i>
                    <span class="jv-label">Saved</span>
                    @if($wishlistCount > 0)
                        <span class="jv-count" id="wishlist-count">{{ $wishlistCount }}</span>
                    @endif
                </a>

                {{-- Cart --}}
                <a href="{{ route('cart.view') }}" class="jv-action">
                    <i class="fa fa-shopping-bag"></i>
                    <span class="jv-label">Cart</span>
                    <span class="jv-count {{ $cartCount > 0 ? '' : 'd-none' }}" id="cart-count">{{ $cartCount }}</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Nav row --}}
    <div class="jv-nav">
        <div class="container">
            <div class="jv-nav-row align-items-center">
                {{-- Opens the categories sidebar as an offcanvas on every screen size. --}}
                @if ($showCategorySidebar ?? true)
                <a href="{{ route('categories.index') }}" class="jv-allcat" data-bs-toggle="offcanvas" data-bs-target="#jvCatSidebar" aria-controls="jvCatSidebar" role="button" aria-expanded="false">
                    <i class="fa-solid fa-bars"></i> All Categories
                </a>
                @endif

                @if (!empty($navCategories) && $navCategories->count() && ($showCategoryStrip ?? true))
                    <div class="jv-catstrip-shell d-none d-lg-flex" data-jv-catstrip-shell>
                        <button type="button" class="jv-catstrip-arrow jv-catstrip-prev" data-jv-catstrip-prev aria-label="Scroll categories left" aria-controls="jvCatStrip">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>

                        <div class="jv-catstrip" id="jvCatStrip" data-jv-catstrip>
                            @foreach ($navCategories as $cat)
                                @if ($cat->children->count())
                                    <div class="dropdown">
                                        <a class="jv-navlink" href="{{ route('category.show', $cat->slug) }}">
                                            <i class="{{ $jvCatIcons[$cat->slug] ?? 'fa-solid fa-tag' }} jv-catstrip-icon" aria-hidden="true"></i>
                                            {{ $cat->translation->name ?? 'Category' }} <i class="fa-solid fa-angle-down ms-1" style="font-size:10px;"></i>
                                        </a>
                                        <ul class="dropdown-menu">
                                            @foreach ($cat->children as $child)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('category.show', $child->slug) }}">
                                                        <i class="{{ $jvCatIcons[$child->slug] ?? 'fa-solid fa-tag' }} jv-catstrip-icon" aria-hidden="true"></i>
                                                        {{ $child->translation->name ?? 'Category' }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @else
                                    <a class="jv-navlink" href="{{ route('category.show', $cat->slug) }}">
                                        <i class="{{ $jvCatIcons[$cat->slug] ?? 'fa-solid fa-tag' }} jv-catstrip-icon" aria-hidden="true"></i>
                                        {{ $cat->translation->name ?? 'Category' }}
                                    </a>
                                @endif
                            @endforeach
                        </div>

                        <button type="button" class="jv-catstrip-arrow jv-catstrip-next" data-jv-catstrip-next aria-label="Scroll categories right" aria-controls="jvCatStrip">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                @endif

                <div class="ms-auto d-flex align-items-center">
                    <form action="{{ route('change.currency') }}" method="POST">
                        @csrf
                        <select name="currency_code" class="jv-select" style="padding:5px 8px;font-size:12px;" onchange="this.form.submit()">
                            @foreach (\App\Models\Currency::all() as $currency)
                                <option value="{{ $currency->code }}" {{ session('currency', getWebConfig('default_currency', 'TZS')) == $currency->code ? 'selected' : '' }}>
                                    {{ strtoupper($currency->code) }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
(function () {
    var shell = document.querySelector('[data-jv-catstrip-shell]');
    if (!shell) return;

    var strip = shell.querySelector('[data-jv-catstrip]');
    var prev = shell.querySelector('[data-jv-catstrip-prev]');
    var next = shell.querySelector('[data-jv-catstrip-next]');
    if (!strip || !prev || !next) return;

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function maxScroll() {
        return Math.max(0, strip.scrollWidth - strip.clientWidth);
    }

    function update() {
        var max = maxScroll();
        var atStart = strip.scrollLeft <= 2;
        var atEnd = strip.scrollLeft >= max - 2;
        var noOverflow = max <= 2;

        prev.disabled = atStart || noOverflow;
        next.disabled = atEnd || noOverflow;
        prev.classList.toggle('is-hidden', prev.disabled);
        next.classList.toggle('is-hidden', next.disabled);
    }

    function step() {
        return Math.max(180, Math.round(strip.clientWidth * 0.65));
    }

    function scrollBy(direction) {
        strip.scrollBy({
            left: direction * step(),
            behavior: reduceMotion ? 'auto' : 'smooth'
        });
    }

    prev.addEventListener('click', function () { scrollBy(-1); });
    next.addEventListener('click', function () { scrollBy(1); });
    strip.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);

    if (window.ResizeObserver) {
        new ResizeObserver(update).observe(strip);
    }

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(update);
    }

    update();
})();
</script>

{{-- All-categories sidebar (offcanvas on every screen size) --}}
@if ($showCategorySidebar ?? true)
<div class="offcanvas offcanvas-start jv-cat-offcanvas" tabindex="-1" id="jvCatSidebar" aria-labelledby="jvCatSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="jvCatSidebarLabel"><i class="fa-solid fa-bars me-2"></i>All Categories</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        @php $jvSidebarSections = \App\Models\HomepageSetting::json('sidebar_sections'); @endphp
        @if (!empty($jvSidebarSections))
            <div class="jv-cat-extra">
                @foreach ($jvSidebarSections as $jvSection)
                    <div class="jv-cat-extra-block">
                        <div class="jv-cat-extra-title">
                            @if (!empty($jvSection['icon']))
                                <i class="{{ $jvSection['icon'] }} me-2"></i>
                            @endif
                            {{ $jvSection['title'] }}
                        </div>
                        <ul class="jv-cat-extra-links">
                            @foreach ($jvSection['links'] as $jvLink)
                                <li>
                                    <a href="{{ $jvLink['url'] }}">
                                        <span>{{ $jvLink['label'] }}</span>
                                        @if (!empty($jvLink['badge']))
                                            <span class="jv-cat-badge">{{ $jvLink['badge'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <hr class="jv-cat-split">
        @endif

        @if (!empty($navCategories) && $navCategories->count())
            <ul class="jv-cat-list">
                @include('themes.xylo.partials.category-nav', ['items' => $navCategories, 'level' => 0])
            </ul>
            <a class="jv-allcat d-block text-center mt-3" style="border-radius:10px;" href="{{ route('categories.index') }}">
                Browse all categories <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
            <a class="jv-allcat d-block text-center mt-2" style="border-radius:10px; background: var(--jv-dark, #1f2937);" href="{{ route('shop.index') }}">
                All Products <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        @else
            <p class="text-muted mb-0">{{ __('store.header.no_categories') ?? 'No categories available yet.' }}</p>
        @endif
    </div>
</div>
@endif
