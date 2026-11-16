@extends('admin.layouts.login')

@section('content')
<div class="container-wrapper" style="max-width:450px;margin:0 auto;padding:20px;">
    <div class="login-container" style="background:#fff;padding:40px;border-radius:10px;box-shadow:0 4px 8px rgba(0,0,0,.1);">
        <h2 class="text-center mb-4">{{ cms_translate('auth.reset_password') ?? 'Reset Password' }}</h2>

        <form method="POST" action="{{ route('password.update') }}" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label for="email" class="form-label">{{ cms_translate('auth.email') }}</label>
                <input type="email" name="email" id="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $email) }}" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">{{ cms_translate('auth.password') }}</label>
                <input type="password" name="password" id="password"
                       class="form-control @error('password') is-invalid @enderror" required>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password-confirm" class="form-label">{{ cms_translate('auth.confirm_password') ?? 'Confirm Password' }}</label>
                <input type="password" name="password_confirmation" id="password-confirm"
                       class="form-control" required>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ cms_translate('auth.reset_password') ?? 'Reset Password' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
