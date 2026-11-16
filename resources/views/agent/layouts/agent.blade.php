<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'JTeam Console') — {{ config('app.name', 'Juvo') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/juvo_logo_3d.png') }}">
    <link href="{{ asset('vendor/fonts/nunito.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/css/bootstrap-icons.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/jteam.css') }}?v={{ @filemtime(public_path('css/jteam.css')) }}">
    @yield('css')
</head>
<body class="jt-body">
@php
    $jteamUser = auth()->user();
    $jteamInitials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($agentProfile->name ?? $jteamUser->name ?? 'J', 0, 1));
@endphp
<div class="d-flex" style="min-height:100vh;">
    <aside class="jt-sidebar" id="jtSidebar">
        <div class="jt-brand d-flex align-items-center gap-2">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" height="34">
            <div>
                <strong>JTeam</strong>
                <small>Sales Console</small>
            </div>
        </div>
        <nav class="jt-nav">
            @php
                $tabs = [
                    ['route'=>'agent.dashboard','label'=>'Dashboard','icon'=>'fas fa-gauge-high'],
                    ['route'=>'agent.customers.index','label'=>'My Customers','icon'=>'fas fa-users'],
                    ['route'=>'agent.leads.index','label'=>'Leads & Prospects','icon'=>'fas fa-user-plus'],
                    ['route'=>'agent.shop.index','label'=>'Shop / Catalogue','icon'=>'fas fa-store'],
                    ['route'=>'agent.place-order.index','label'=>'Place Customer Order','icon'=>'fas fa-cart-plus'],
                    ['route'=>'agent.orders.index','label'=>'My Orders','icon'=>'fas fa-receipt'],
                    ['route'=>'agent.performance.index','label'=>'My Sales & Performance','icon'=>'fas fa-chart-line'],
                    ['route'=>'agent.commissions.index','label'=>'Commissions & Bonuses','icon'=>'fas fa-coins'],
                    ['route'=>'agent.referrals.index','label'=>'My Team / Referrals','icon'=>'fas fa-people-group'],
                    ['route'=>'agent.deals.index','label'=>'Deals & Promotions','icon'=>'fas fa-tags'],
                    ['route'=>'agent.academy.index','label'=>'Juvo Academy','icon'=>'fas fa-graduation-cap'],
                    ['route'=>'agent.community.index','label'=>'Community & Updates','icon'=>'fas fa-bullhorn'],
                    ['route'=>'agent.support.index','label'=>'Help & Support','icon'=>'fas fa-headset'],
                    ['route'=>'agent.profile.index','label'=>'My Profile & Settings','icon'=>'fas fa-user-gear'],
                ];
            @endphp
            @foreach($tabs as $tab)
                <a href="{{ route($tab['route']) }}" class="{{ request()->routeIs($tab['route']) ? 'active' : '' }}">
                    <i class="{{ $tab['icon'] }}"></i><span>{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="px-3 py-3 border-top" style="border-color:rgba(255,255,255,.08)!important;">
            <form action="{{ route('agent.logout') }}" method="POST">
                @csrf
                <button class="btn btn-sm btn-outline-light w-100">Sign out</button>
            </form>
        </div>
    </aside>

    <div class="jt-main flex-grow-1 d-flex flex-column" style="min-width:0;">
        <header class="jt-topbar px-3 px-md-4 py-3 d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none" type="button" id="jtSidebarToggle" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="fw-bold" style="font-size:16px;">@yield('title', 'JTeam Console')</div>
                <div class="jt-muted" style="font-size:12px;">{{ now()->format('l, d M Y') }}</div>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="{{ route('agent.profile.index') }}" class="jt-avatar text-decoration-none" title="{{ $agentProfile->name ?? $jteamUser->name }}">{{ $jteamInitials }}</a>
            </div>
        </header>
        <main class="p-3 p-md-4 flex-grow-1">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script>
    (function () {
        const toggle = document.getElementById('jtSidebarToggle');
        const sidebar = document.getElementById('jtSidebar');
        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('is-open');
            });
            document.addEventListener('click', function (event) {
                if (window.innerWidth < 992 && sidebar.classList.contains('is-open') && !sidebar.contains(event.target) && event.target !== toggle && !toggle.contains(event.target)) {
                    sidebar.classList.remove('is-open');
                }
            });
        }
    })();
</script>
@yield('js')
</body>
</html>
