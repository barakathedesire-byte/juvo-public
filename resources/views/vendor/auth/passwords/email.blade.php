@extends('vendor.auth.layout')
@section('title', 'Forgot password — Juvo Vendor')

@section('panel')
    <h2>Reset your password</h2>
    <p class="juvo-portal-sub">Enter your vendor email and we'll send you a reset link.</p>

    @if (session('status'))
        <div class="alert alert-success py-2" role="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('vendor.password.email') }}" autocomplete="off">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" placeholder="you@store.com" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="juvo-portal-submit">Send reset link</button>
    </form>

    <p class="juvo-portal-foot-links">
        Remembered it? <a href="{{ route('vendor.login') }}">Back to vendor sign in</a><br>
        Shopping instead? <a href="{{ storefront_url('/customer/login') }}">Buyer sign in</a>
    </p>
@endsection
