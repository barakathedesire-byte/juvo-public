@extends('themes.xylo.layouts.auth')
@section('title', 'Verify your email — Juvo')

@section('content')
<div class="jv-auth-wrap">
    <div class="jv-auth-card" style="max-width: 480px;">
        <div class="jv-auth-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" height="46"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <span class="jv-wordmark" style="display:none;">Ju<span>vo</span></span>
        </div>

        <h2>Verify your email</h2>
        <p class="jv-sub">We emailed a 6-digit code to <strong>{{ $email }}</strong>. Enter it below to confirm your account.</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if(!empty($debugCode))
            <div class="alert alert-warning small">
                <strong>Development mode:</strong> your code is <code>{{ $debugCode }}</code> (also logged by the mailer).
            </div>
        @endif

        <form method="POST" action="{{ route('customer.verification.verify') }}">
            @csrf
            <div class="jv-field">
                <label for="code">Verification code</label>
                <input id="code" type="text" name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                       class="jv-input" placeholder="123456" style="text-align:center;letter-spacing:8px;font-size:22px;" required autofocus>
            </div>
            <button type="submit" class="jv-btn jv-btn-primary jv-btn-block">Verify email</button>
        </form>

        <form method="POST" action="{{ route('customer.verification.resend') }}" class="mt-3 text-center">
            @csrf
            <button type="submit" class="btn btn-link p-0">Resend code</button>
        </form>
    </div>
</div>
@endsection