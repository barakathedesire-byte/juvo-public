@extends('themes.xylo.layouts.auth')
@section('title', 'Forgot password — Juvo')

@section('content')
<div class="jv-auth-wrap">
    <div class="jv-auth-card">
        <div class="jv-auth-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" height="46"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <span class="jv-wordmark" style="display:none;">Ju<span>vo</span></span>
        </div>

        <h2>Reset your password</h2>
        <p class="jv-sub">Enter your email and we'll send you a reset link.</p>

        @if (session('status'))
            <div class="jv-alert jv-alert-success mb-3">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('customer.password.email') }}">
            @csrf

            <div class="jv-field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       class="jv-input" placeholder="you@example.com" required autofocus>
                @error('email') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="jv-btn jv-btn-primary jv-btn-block">Send reset link</button>
        </form>

        <p class="jv-auth-foot">
            Remembered it? <a href="{{ route('customer.login') }}">Back to buyer sign in</a><br>
            Selling instead? <a href="{{ route('vendor.login') }}">Vendor sign in</a>
        </p>
    </div>
</div>
@endsection
