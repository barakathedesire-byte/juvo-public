<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f68b1e">
    <title>@yield('title', 'Vendor — Juvo')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/juvo_logo_3d.png') }}">
    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @include('themes.xylo.partials.portal-auth-style')
</head>
<body class="juvo-portal">
<div class="juvo-portal-shell">
    <aside class="juvo-portal-brand">
        <div class="juvo-portal-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" onerror="this.style.display='none';">
            <span class="juvo-portal-word">Ju<span>vo</span></span>
        </div>

        <div class="juvo-portal-copy">
            <span class="juvo-portal-tag"><i class="fa-solid fa-store"></i> @yield('tag', 'Vendor Portal')</span>
            <h1>@yield('heading', 'Sell to millions on Juvo.')</h1>
            <p>@yield('copy', 'Sign in to manage your store, products, orders, payouts and ads — all in one place.')</p>
        </div>

        <div class="juvo-portal-foot">&copy; {{ date('Y') }} Juvo Marketplace</div>
    </aside>

    <main class="juvo-portal-panel">
        <div class="juvo-portal-card">
            <div class="juvo-portal-smmark">
                <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" onerror="this.style.display='none';">
                <span class="juvo-portal-word">Ju<span>vo</span></span>
            </div>

            @yield('panel')
        </div>
    </main>
</div>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
