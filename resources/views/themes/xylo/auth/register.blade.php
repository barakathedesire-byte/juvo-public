@extends('themes.xylo.layouts.auth')
@section('title', 'Create account — Juvo')

@section('content')
<div class="jv-auth-wrap">
    <div class="jv-auth-card">
        <div class="jv-auth-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" height="46"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <span class="jv-wordmark" style="display:none;">Ju<span>vo</span></span>
        </div>

        <div class="jv-tabs-auth">
            <a href="{{ route('customer.register') }}" class="active">Buyer</a>
            <a href="{{ route('vendor.register') }}">Vendor</a>
        </div>

        <h2>Create a buyer account</h2>
        <p class="jv-sub">Shop millions of products. It only takes a minute.</p>

        @if (\App\Support\Integrations\GoogleSignIn::isEnabled())
        <div class="jv-social">
            <a href="{{ route('customer.auth.google.redirect') }}" class="jv-btn jv-btn-google jv-btn-block">
                <i class="fa-brands fa-google" style="color:#ea4335;"></i> Sign up with Google
            </a>
        </div>

        <div class="jv-divider-or">or sign up with email</div>
        @endif

        <form method="POST" action="{{ route('customer.register') }}">
            @csrf

            <div class="jv-field">
                <label for="name">Full name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}"
                       class="jv-input" placeholder="Jane Doe" required>
                @error('name') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       class="jv-input" placeholder="you@example.com" required>
                @error('email') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="phone">Phone number</label>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}"
                       class="jv-input" placeholder="+255..." required>
                @error('phone') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="national_id">National ID</label>
                <input id="national_id" type="text" name="national_id" value="{{ old('national_id') }}"
                       class="jv-input" placeholder="National ID number" required>
                @error('national_id') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="address">Address</label>
                <textarea id="address" name="address" class="jv-input" rows="2" required>{{ old('address') }}</textarea>
                @error('address') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="next_of_kin_name">Next of kin name</label>
                <input id="next_of_kin_name" type="text" name="next_of_kin_name" value="{{ old('next_of_kin_name') }}"
                       class="jv-input" placeholder="Next of kin full name" required>
                @error('next_of_kin_name') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="next_of_kin_phone">Next of kin phone number</label>
                <input id="next_of_kin_phone" type="text" name="next_of_kin_phone" value="{{ old('next_of_kin_phone') }}"
                       class="jv-input" placeholder="Next of kin phone" required>
                @error('next_of_kin_phone') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" class="jv-input"
                       placeholder="At least 8 characters" required>
                @error('password') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="jv-field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       class="jv-input" placeholder="Repeat password" required>
            </div>

            <button type="submit" class="jv-btn jv-btn-primary jv-btn-block">Create account</button>
        </form>

        <p class="jv-auth-foot">
            Already have an account? <a href="{{ route('customer.login') }}">Sign in</a><br>
            Want to sell? <a href="{{ route('vendor.register') }}">Open a vendor store</a>
        </p>
    </div>
</div>
@endsection
