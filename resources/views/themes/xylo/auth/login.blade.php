@extends('themes.xylo.layouts.auth')
@section('title', 'Sign in — Juvo')

@section('content')
<div class="jv-auth-wrap">
    <div class="jv-auth-card">
        <div class="jv-auth-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" height="46"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <span class="jv-wordmark" style="display:none;">Ju<span>vo</span></span>
        </div>

        <h2>Sign in to Juvo</h2>
        <p class="jv-sub">Welcome back — sign in to continue shopping.</p>

        @if (\App\Support\Integrations\GoogleSignIn::isEnabled())
        <div class="jv-social">
            <a href="{{ route('customer.auth.google.redirect') }}" class="jv-btn jv-btn-google jv-btn-block">
                <i class="fa-brands fa-google" style="color:#ea4335;"></i> Continue with Google
            </a>
        </div>

        <div class="jv-divider-or">or sign in with email</div>
        @endif

        <form method="POST" action="{{ route('customer.login') }}">
            @csrf

            <div class="jv-field">
                <label for="email">Email or username</label>
                <input id="email" type="text" name="email" value="{{ old('email') }}"
                       class="jv-input" placeholder="you@example.com" required autofocus>
                @error('email') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" class="jv-input"
                       placeholder="Your password" required>
                @error('password') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <label class="d-flex align-items-center gap-2" style="font-size:13px;">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="{{ route('customer.password.request') }}" style="font-size:13px;color:var(--jv-orange);font-weight:600;">Forgot password?</a>
            </div>

            <button type="submit" class="jv-btn jv-btn-primary jv-btn-block">Sign in</button>
        </form>

        <p class="jv-auth-foot">
            New to Juvo? <a href="{{ route('customer.register') }}">Create a buyer account</a><br>
            Want to sell? <a href="{{ route('vendor.register') }}">Open a vendor store</a>
        </p>
    </div>
</div>
@endsection
