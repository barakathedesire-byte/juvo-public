@extends('vendor.auth.layout')
@section('title', 'Reset password — Juvo Vendor')

@section('panel')
    <h2>Choose a new password</h2>
    <p class="juvo-portal-sub">Set a new password for your Juvo vendor account.</p>

    <form method="POST" action="{{ route('vendor.password.update') }}" autocomplete="off">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}"
                   class="form-control @error('email') is-invalid @enderror" placeholder="you@store.com" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">New password</label>
            <input id="password" type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror" placeholder="At least 8 characters" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   class="form-control" placeholder="Repeat the new password" required>
        </div>

        <button type="submit" class="juvo-portal-submit">Reset password</button>
    </form>

    <p class="juvo-portal-foot-links">
        <a href="{{ route('vendor.login') }}">Back to vendor sign in</a>
    </p>
@endsection
