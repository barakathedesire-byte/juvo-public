<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1220">
    <title>Juvo Logistics | @yield('title', 'Dashboard')</title>
    <link href="{{ asset('vendor/fonts/nunito.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/logistics.css') }}?v=20261106" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/panel-bubble.css') }}">
    @stack('styles')
</head>
<body class="logistics-portal">
    @php $admin = Auth::guard('logistics_admin')->user(); @endphp

    <aside class="sidebar" id="sidebar">
        <a class="sidebar-brand" href="{{ route('logistics.dashboard') }}" style="flex-direction: column; align-items: center; text-align: center; gap: .5rem;">
            <div class="logo-container">
                <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" style="height:44px;width:auto;flex:0 0 auto;" onerror="this.style.display='none'">
            </div>
            <span class="sidebar-brand-copy">
                <h3>Juvo Logistics</h3>
                <small>Operations portal</small>
            </span>
        </a>

        <nav class="nav flex-column" aria-label="Logistics navigation">
            @php
                $admin = auth('logistics_admin')->user();
                $can = fn (string $module) => $admin && $admin->canAccessLogisticsModule($module);
            @endphp

            <div class="nav-label">Operations</div>
            @if ($can('dashboard'))
                <a class="nav-link {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}" href="{{ route('logistics.dashboard') }}" title="Dashboard">
                    <i class="fas fa-gauge-high"></i><span>Dashboard</span>
                </a>
            @endif
            @if ($can('orders'))
                <a class="nav-link {{ request()->routeIs('logistics.orders.*') ? 'active' : '' }}" href="{{ route('logistics.orders.index') }}" title="Orders">
                    <i class="fas fa-boxes-stacked"></i><span>Orders</span>
                </a>
            @endif
            @if ($can('dispatch'))
                <a class="nav-link {{ request()->routeIs('logistics.dispatch.*') ? 'active' : '' }}" href="{{ route('logistics.dispatch.index') }}" title="Dispatch & Allocation">
                    <i class="fas fa-route"></i><span>Dispatch &amp; Allocation</span>
                </a>
            @endif
            @if ($can('dispatch'))
                <a class="nav-link {{ request()->routeIs('logistics.route-optimization.*') ? 'active' : '' }}" href="{{ route('logistics.route-optimization.index') }}" title="Route Optimization">
                    <i class="fas fa-map-marked-alt"></i><span>Route Optimization</span>
                </a>
            @endif
            @if ($can('tracking'))
                <a class="nav-link {{ request()->routeIs('logistics.tracking.*') ? 'active' : '' }}" href="{{ route('logistics.tracking.index') }}" title="Live Tracking & Fleet">
                    <i class="fas fa-location-crosshairs"></i><span>Live Tracking &amp; Fleet</span>
                </a>
            @endif
            @if ($can('riders'))
                <a class="nav-link {{ request()->routeIs('logistics.riders.*') ? 'active' : '' }}" href="{{ route('logistics.riders.index') }}" title="Riders Management">
                    <i class="fas fa-motorcycle"></i><span>Riders Management</span>
                </a>
            @endif

            <div class="nav-label">Finance</div>
            @if ($can('finance'))
                <a class="nav-link {{ request()->routeIs('logistics.finance.*') ? 'active' : '' }}" href="{{ route('logistics.finance.index') }}" title="Cash & COD">
                    <i class="fas fa-money-bill-transfer"></i><span>Cash &amp; COD</span>
                </a>
            @endif
            @if ($can('payouts'))
                <a class="nav-link {{ request()->routeIs('logistics.payouts.*') ? 'active' : '' }}" href="{{ route('logistics.payouts.index') }}" title="Payouts & Settlements">
                    <i class="fas fa-wallet"></i><span>Payouts &amp; Settlements</span>
                </a>
            @endif

            <div class="nav-label">Management &amp; Intelligence</div>
            @if ($can('zones'))
                <a class="nav-link {{ request()->routeIs('logistics.zones.*') ? 'active' : '' }}" href="{{ route('logistics.zones.index') }}" title="Zones, Routes & Hubs">
                    <i class="fas fa-map-location-dot"></i><span>Zones, Routes &amp; Hubs</span>
                </a>
            @endif
            @if ($can('analytics'))
                <a class="nav-link {{ request()->routeIs('logistics.analytics.*') ? 'active' : '' }}" href="{{ route('logistics.analytics.index') }}" title="Analytics & Reports">
                    <i class="fas fa-chart-line"></i><span>Analytics &amp; Reports</span>
                </a>
            @endif
            @if ($can('analytics'))
                <a class="nav-link {{ request()->routeIs('logistics.predictive.*') ? 'active' : '' }}" href="{{ route('logistics.predictive.index') }}" title="Predictive Intelligence">
                    <i class="fas fa-brain"></i><span>Predictive Intelligence</span>
                </a>
            @endif
            @if ($can('zones'))
                <a class="nav-link {{ request()->routeIs('logistics.hubs.*') ? 'active' : '' }}" href="{{ route('logistics.hubs.index') }}" title="Hubs">
                    <i class="fas fa-warehouse"></i><span>Hubs</span>
                </a>
                <a class="nav-link {{ request()->routeIs('logistics.pricing.*') ? 'active' : '' }}" href="{{ route('logistics.pricing.index') }}" title="Delivery Pricing">
                    <i class="fas fa-tags"></i><span>Delivery Pricing</span>
                </a>
                <a class="nav-link {{ request()->routeIs('logistics.routes.*') ? 'active' : '' }}" href="{{ route('logistics.routes.index') }}" title="Routes">
                    <i class="fas fa-route"></i><span>Routes</span>
                </a>
            @endif
            @if ($can('analytics'))
                <a class="nav-link {{ request()->routeIs('logistics.reports.*') ? 'active' : '' }}" href="{{ route('logistics.reports.index') }}" title="Reports">
                    <i class="fas fa-file-export"></i><span>Reports</span>
                </a>
            @endif
            @if ($can('support'))
                <a class="nav-link {{ request()->routeIs('logistics.support.*') ? 'active' : '' }}" href="{{ route('logistics.support.index') }}" title="Customer Support & Disputes">
                    <i class="fas fa-headset"></i><span>Customer Support &amp; Disputes</span>
                </a>
            @endif
            @if ($can('support'))
                <a class="nav-link {{ request()->routeIs('logistics.fraud.*') ? 'active' : '' }}" href="{{ route('logistics.fraud.index') }}" title="Fraud & Risk Intelligence">
                    <i class="fas fa-shield-halved"></i><span>Fraud &amp; Risk</span>
                </a>
            @endif
            @if ($can('notifications'))
                <a class="nav-link {{ request()->routeIs('logistics.notifications.*') ? 'active' : '' }}" href="{{ route('logistics.notifications.index') }}" title="Notifications & Automation">
                    <i class="fas fa-bell"></i><span>Notifications &amp; Automation</span>
                </a>
            @endif

            <div class="nav-label">System</div>
            @if ($can('settings'))
                <a class="nav-link {{ request()->routeIs('logistics.settings.*') ? 'active' : '' }}" href="{{ route('logistics.settings.index') }}" title="Settings & Administration">
                    <i class="fas fa-sliders"></i><span>Settings &amp; Administration</span>
                </a>
            @endif
            @if ($can('settings'))
                <a class="nav-link {{ request()->routeIs('logistics.rider-builder.*') ? 'active' : '' }}" href="{{ route('logistics.rider-builder.index') }}" title="Rider Builder">
                    <i class="fas fa-motorcycle"></i><span>Rider Builder</span>
                </a>
            @endif
            @if ($can('settings'))
                <a class="nav-link {{ request()->routeIs('logistics.integration.*') ? 'active' : '' }}" href="{{ route('logistics.integration.index') }}" title="Integration Monitoring">
                    <i class="fas fa-plug"></i><span>Integration Monitoring</span>
                </a>
            @endif
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <img src="{{ $admin->profile_image ? asset('storage/'.$admin->profile_image) : 'https://ui-avatars.com/api/?name='.urlencode($admin->name).'&background=f68b1e&color=fff' }}" class="avatar" alt="{{ $admin->name }}">
                <div class="meta">
                    <strong>{{ $admin->name }}</strong>
                    <span>{{ ucfirst(str_replace('_', ' ', $admin->role ?? 'Administrator')) }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('logistics.logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="nav-link text-danger border-0 bg-transparent w-100 text-start">
                    <i class="fas fa-arrow-right-from-bracket"></i><span>Sign out</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <main id="content" class="main-content page-transition">
        <header class="topbar navbar navbar-expand navbar-light bg-light p-3">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button type="button" class="icon-button sidebar-toggle" id="sidebarToggle" aria-label="Open navigation">
                    <i class="fas fa-bars"></i>
                </button>
                <button type="button" class="icon-button sidebar-collapse-btn" id="sidebarCollapse" aria-label="Collapse navigation" title="Collapse navigation">
                    <i class="fas fa-bars-staggered"></i>
                </button>
                <div class="topbar-title d-flex align-items-center gap-2">
                    <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" style="height:30px;width:auto;" onerror="this.style.display='none'">
                    <div>
                    <div class="eyebrow">Logistics operations</div>
                    <h1>@yield('heading', 'Dashboard')</h1>
                    @hasSection('breadcrumb')
                        <nav class="breadcrumb-nav" aria-label="Breadcrumb">@yield('breadcrumb')</nav>
                    @endif
                    <p>Welcome back, {{ Str::of($admin->name)->before(' ') }}</p>
                    </div>
                </div>
            </div>

            <form class="topbar-search" method="GET" action="{{ route('logistics.orders.index') }}" role="search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" name="q" value="{{ request()->routeIs('logistics.orders.*') ? request('q') : '' }}" placeholder="Search tracking ID or order #" aria-label="Search orders">
            </form>

            <div class="topbar-actions">
                <div class="dropdown">
                    <button type="button" class="icon-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Recent activity">
                        <i class="far fa-bell"></i>
                        @if ($pendingDispatchCount > 0)
                            <span class="dot"></span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" style="width: min(340px, calc(100vw - 2rem));">
                        <div class="d-flex align-items-center justify-content-between px-2 py-1">
                            <strong class="small">Operations activity</strong>
                            <a href="{{ route('logistics.orders.index', ['status' => 'pending']) }}" class="small link-juvo">{{ $pendingDispatchCount }} pending</a>
                        </div>
                        @forelse ($recentDeliveryEvents as $event)
                            <a class="dropdown-item d-flex gap-2 py-2" href="{{ route('logistics.orders.show', $event->order_id) }}">
                                <span class="activity-dot"><i class="fas fa-route"></i></span>
                                <span class="min-w-0">
                                    <span class="d-block small fw-semibold text-truncate">Order #{{ $event->order_id }} · {{ ucfirst(str_replace('_', ' ', $event->type)) }}</span>
                                    <span class="d-block text-muted" style="font-size: .66rem;">{{ $event->actor_name ?: 'System' }} · {{ $event->created_at?->diffForHumans() }}</span>
                                </span>
                            </a>
                        @empty
                            <div class="empty-state py-4">
                                <div class="icon"><i class="far fa-bell-slash"></i></div>
                                <strong>No operational activity yet</strong>
                                <p>Delivery events will appear here as orders move.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="dropdown">
                    <button type="button" class="profile-trigger" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="{{ $admin->profile_image ? asset('storage/'.$admin->profile_image) : 'https://ui-avatars.com/api/?name='.urlencode($admin->name).'&background=f68b1e&color=fff' }}" class="avatar" alt="{{ $admin->name }}">
                        <span class="name">{{ $admin->name }}</span>
                        <i class="fas fa-chevron-down text-muted" style="font-size: .6rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-2 py-1">
                            <div class="small fw-semibold">{{ $admin->name }}</div>
                            <div class="text-muted" style="font-size: .66rem;">{{ $admin->email }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('logistics.profile') }}"><i class="far fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="{{ route('logistics.settings.index') }}"><i class="fas fa-sliders me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logistics.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-arrow-right-from-bracket me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show mb-4" role="status">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <strong>Check the form:</strong>
                <ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/panel-bubble.js') }}"></script>
    <script>
        (function () {
            const body = document.body;
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const toggle = document.getElementById('sidebarToggle');
            const collapse = document.getElementById('sidebarCollapse');

            const closeSidebar = () => {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
                toggle?.setAttribute('aria-expanded', 'false');
            };

            toggle?.addEventListener('click', () => {
                const open = sidebar.classList.toggle('show');
                backdrop.classList.toggle('show', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            backdrop?.addEventListener('click', closeSidebar);

            if (localStorage.getItem('juvo-sidebar') === 'collapsed') {
                body.classList.add('sidebar-collapsed');
            }

            collapse?.addEventListener('click', () => {
                const collapsed = body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('juvo-sidebar', collapsed ? 'collapsed' : 'expanded');
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 992) closeSidebar();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeSidebar();
            });
        })();
    </script>
    <script>
        (function () {
            var key = 'juvo-logistics-sidebar-scroll';
            var sidebar = document.getElementById('sidebar');
            if (!sidebar) return;

            try {
                var saved = sessionStorage.getItem(key);
                if (saved !== null) sidebar.scrollTop = parseInt(saved, 10) || 0;
            } catch (e) {}

            var save = function () {
                try { sessionStorage.setItem(key, String(sidebar.scrollTop || 0)); } catch (e) {}
            };

            sidebar.addEventListener('scroll', save, { passive: true });
            document.addEventListener('click', function (event) {
                if (event.target && event.target.closest && event.target.closest('#sidebar a')) save();
            }, true);
            window.addEventListener('pagehide', save);
            window.addEventListener('beforeunload', save);
        })();
    </script>
    @stack('scripts')
</body>
</html>
