<!DOCTYPE html>
<html lang="en">
<head>
@php
    $riderBuilder = \App\Support\Rider\RiderBuilderContent::forDisplay();
@endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $riderBuilder['primary_color'] }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-web-app-status-bar-style" content="black-translucent">
    <title>{{ $riderBuilder['app_title'] ?: $riderBuilder['brand_name'] }} | @yield('title', 'Dashboard')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/rider-app.css') }}?v={{ @filemtime(public_path('css/rider-app.css')) }}" rel="stylesheet">
    <style>
        :root {
            --juvo-orange: {{ $riderBuilder['primary_color'] }};
            --juvo-orange-dark: {{ $riderBuilder['primary_dark'] }};
            --juvo-orange-light: {{ $riderBuilder['primary_light'] }};
            --juvo-canvas: {{ $riderBuilder['canvas_color'] }};
            --juvo-success: {{ $riderBuilder['success_color'] }};
            --juvo-danger: {{ $riderBuilder['danger_color'] }};
        }
    </style>
    @stack('styles')
</head>
<body class="@yield('body_class')">
    @php
    $hideLegacyHeader = request()->routeIs('rider.dashboard', 'rider.orders', 'rider.deliveries.*', 'rider.earnings', 'rider.profile')
        || str_contains((string) trim($__env->yieldContent('body_class')), 'rider-profile');
@endphp
@unless ($hideLegacyHeader || View::hasSection('hide_legacy_header'))
        <header class="rider-header">
            <div class="rider-header-inner">
                <a class="rider-brand" href="{{ route('rider.dashboard') }}">
                    <span class="rider-brand-mark"><i class="{{ $riderBuilder['brand_icon'] ?: 'fas fa-motorcycle' }}"></i></span>
                    <span>
                        <strong>{{ $riderBuilder['brand_name'] }}</strong>
                        <span>{{ $riderBuilder['brand_tagline'] }}</span>
                    </span>
                </a>

                <div class="rider-header-right">
                    <div class="rider-greeting">
                        <p class="rider-greeting-text">Good morning, <span>{{ Str::upper(Str::substr($rider->first_name, 0, 1)).Str::substr($rider->last_name ?? '', 0, 1) }} 👋</span></p>
                        <p class="rider-greeting-sub">{{ $riderBuilder['hero_greeting_suffix'] }}</p>
                    </div>

                    <div class="rider-online-pill">
                        <span class="rider-online-dot {{ $rider->is_online ? 'online' : 'offline' }}"></span>
                        <span id="onlineLabel">{{ $rider->is_online ? 'Online' : 'Offline' }}</span>
                    </div>

                    <a href="{{ route('rider.dashboard') }}#notifications" class="rider-bell-btn" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>
        </header>
    @endunless

    @yield('hero')

    <main class="rider-shell">
        <div class="alert alert-warning d-none align-items-center gap-2 mb-3" id="offlineAlert" role="status">
            <i class="fas fa-wifi"></i>
            <span>You are offline. Actions will need a connection.</span>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show mb-3" role="status">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <strong>Check the form:</strong>
                <ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <nav class="bottom-nav" aria-label="Rider navigation">
        <div class="bottom-nav-grid">
            @foreach ($riderBuilder['navigation'] as $navItem)
                @if (! ($navItem['enabled'] ?? true))
                    @continue
                @endif
                @php
                    $navRoute = match ($navItem['key']) {
                        'orders' => 'rider.orders',
                        'earnings' => 'rider.earnings',
                        'profile' => 'rider.profile',
                        default => 'rider.dashboard',
                    };
                    $navActive = match ($navItem['key']) {
                        'orders' => request()->routeIs('rider.orders', 'rider.deliveries.*'),
                        'earnings' => request()->routeIs('rider.earnings'),
                        'profile' => request()->routeIs('rider.profile'),
                        default => request()->routeIs('rider.dashboard'),
                    };
                @endphp
                <a href="{{ route($navRoute) }}" class="{{ $navActive ? 'active' : '' }}" @if ($navActive) aria-current="page" @endif>
                    <i class="{{ $navItem['icon'] }}"></i><span>{{ $navItem['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const pill = document.getElementById('onlineLabel');
            const alert = document.getElementById('offlineAlert');
            const baseLabel = @json($rider->is_online ? 'Online' : 'Offline');

            const updateConnection = () => {
                const offline = !navigator.onLine;
                alert?.classList.toggle('d-none', !offline);
                alert?.classList.toggle('d-flex', offline);
                if (pill) pill.textContent = offline ? 'No connection' : baseLabel;
            };

            window.addEventListener('online', updateConnection);
            window.addEventListener('offline', updateConnection);
            updateConnection();
        })();
    </script>
    @stack('scripts')
</body>
</html>
