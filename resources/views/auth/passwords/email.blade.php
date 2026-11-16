@extends('admin.layouts.login')

@section('content')
<div class="container-wrapper" style="max-width:450px;margin:0 auto;padding:20px;">
    <div class="login-container" style="background:#fff;padding:40px;border-radius:10px;box-shadow:0 4px 8px rgba(0,0,0,.1);">
        <h2 class="text-center mb-4">{{ cms_translate('auth.reset_password') ?? 'Reset Password' }}</h2>

        @if (session('status'))
            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" autocomplete="off">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">{{ cms_translate('auth.email') }}</label>
                <input type="email" name="email" id="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autofocus>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ cms_translate('auth.send_password_reset_link') ?? 'Send Password Reset Link' }}</button>
            </div>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('login') }}">{{ cms_translate('auth.login') }}</a>
        </div>
    </div>
</div>
@endsection
