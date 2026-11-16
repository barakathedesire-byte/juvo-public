@extends('admin.layouts.login')

@section('content')
<div class="container-wrapper" style="max-width:450px;margin:0 auto;padding:20px;">
    <div class="login-container" style="background:#fff;padding:40px;border-radius:10px;box-shadow:0 4px 8px rgba(0,0,0,.1);">
        <h2 class="text-center mb-4">{{ cms_translate('auth.confirm_password') ?? 'Confirm Password' }}</h2>
        <p class="text-muted text-center">{{ cms_translate('auth.confirm_password_notice') ?? 'Please confirm your password before continuing.' }}</p>

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <div class="mb-3">
                <label for="password" class="form-label">{{ cms_translate('auth.password') }}</label>
                <input type="password" name="password" id="password"
                       class="form-control @error('password') is-invalid @enderror" required autofocus>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ cms_translate('auth.confirm_password') ?? 'Confirm Password' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
