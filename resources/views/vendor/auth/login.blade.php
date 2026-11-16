<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f68b1e">
    <title>Vendor sign in — Juvo</title>
    <link rel="icon" type="image/png" href="{{ asset('images/juvo_logo_3d.png') }}">
    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @include('themes.xylo.partials.portal-auth-style')
</head>
<body class="juvo-portal {{ session('error') || $errors->any() ? '' : '' }}">
<div class="juvo-portal-shell">
    <aside class="juvo-portal-brand">
        <div class="juvo-portal-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" onerror="this.style.display='none';">
            <span class="juvo-portal-word">Ju<span>vo</span></span>
        </div>

        <div class="juvo-portal-copy">
            <span class="juvo-portal-tag"><i class="fa-solid fa-store"></i> Vendor Portal</span>
            <h1>Sell to millions on Juvo.</h1>
            <p>Sign in to manage your store, products, orders, payouts and ads — all in one place.</p>
        </div>

        <ul class="juvo-portal-points">
            <li><i class="fa-solid fa-circle-check"></i> Track orders and fulfilment in real time</li>
            <li><i class="fa-solid fa-circle-check"></i> Manage inventory, pricing and promotions</li>
            <li><i class="fa-solid fa-circle-check"></i> Get paid with transparent commission</li>
        </ul>

        <div class="juvo-portal-foot">&copy; {{ date('Y') }} Juvo Marketplace</div>
    </aside>

    <main class="juvo-portal-panel">
        <div class="juvo-portal-card">
            <div class="juvo-portal-smmark">
                <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" onerror="this.style.display='none';">
                <span class="juvo-portal-word">Ju<span>vo</span></span>
            </div>

            <div class="juvo-portal-tabs">
                <a href="{{ storefront_url('/customer/login') }}">Buyer</a>
                <a href="{{ route('vendor.login') }}" class="active">Vendor</a>
            </div>

            <h2>Vendor sign in</h2>
            <p class="juvo-portal-sub">Manage your Juvo store, products and orders.</p>

            @if(session('error'))
                <div class="alert alert-danger py-2" role="alert">{{ session('error') }}</div>
            @endif
            @if(session('status'))
                <div class="alert alert-success py-2" role="alert">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('vendor.login.submit') }}" autocomplete="off">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email or username</label>
                    <input id="email" type="text" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror" placeholder="you@store.com" required autofocus>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror" placeholder="Your password" required>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <span class="form-check">
                        <input type="checkbox" class="form-check-input" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </span>
                    <a href="{{ route('vendor.password.request') }}" style="font-size:13px;color:var(--juvo-orange);font-weight:700;text-decoration:none;">Forgot password?</a>
                </div>
                <button type="submit" class="juvo-portal-submit">Sign in to store</button>
            </form>

            <p class="juvo-portal-foot-links">
                New vendor? <a href="{{ route('vendor.register') }}">Open a store on Juvo</a><br>
                Shopping instead? <a href="{{ storefront_url('/customer/login') }}">Buyer sign in</a>
            </p>
        </div>
    </main>
</div>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
