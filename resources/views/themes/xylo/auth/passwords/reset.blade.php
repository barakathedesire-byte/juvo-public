@extends('themes.xylo.layouts.auth')
@section('title', 'Reset password — Juvo')

@section('content')
<div class="jv-auth-wrap">
    <div class="jv-auth-card">
        <div class="jv-auth-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" height="46"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <span class="jv-wordmark" style="display:none;">Ju<span>vo</span></span>
        </div>

        <h2>Choose a new password</h2>
        <p class="jv-sub">Set a new password for your account.</p>

        <form method="POST" action="{{ route('customer.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="jv-field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}"
                       class="jv-input" placeholder="you@example.com" required autofocus>
                @error('email') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="password">New password</label>
                <input id="password" type="password" name="password" class="jv-input"
                       placeholder="At least 8 characters" required>
                @error('password') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       class="jv-input" placeholder="Repeat the new password" required>
            </div>

            <button type="submit" class="jv-btn jv-btn-primary jv-btn-block">Reset password</button>
        </form>

        <p class="jv-auth-foot">
            <a href="{{ route('customer.login') }}">Back to sign in</a>
        </p>
    </div>
</div>
@endsection
