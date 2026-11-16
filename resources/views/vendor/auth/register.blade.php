@extends('themes.xylo.layouts.auth')
@section('title', 'Open a store — Juvo')

@section('content')
<div class="jv-auth-wrap">
    <div class="jv-auth-card" style="max-width: 560px;">
        <div class="jv-auth-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" height="46"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <span class="jv-wordmark" style="display:none;">Ju<span>vo</span></span>
        </div>

        <div class="jv-tabs-auth">
            <a href="{{ storefront_url('/customer/register') }}">Buyer</a>
            <a href="{{ route('vendor.register') }}" class="active">Vendor</a>
        </div>

        <h2>Open your store on Juvo</h2>
        <p class="jv-sub">Reach millions of shoppers. New stores are reviewed by our team before going live.</p>

        <form method="POST" action="{{ route('vendor.register.submit') }}" enctype="multipart/form-data">
            @csrf

            <div class="row">
                <div class="col-md-6 jv-field">
                    <label for="name">Owner full name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}"
                           class="jv-input" placeholder="Jane Doe" required>
                    @error('name') <span class="jv-error">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-6 jv-field">
                    <label for="shop_name">Store name</label>
                    <input id="shop_name" type="text" name="shop_name" value="{{ old('shop_name') }}"
                           class="jv-input" placeholder="Jane's Boutique" required>
                    @error('shop_name') <span class="jv-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 jv-field">
                    <label for="email">Business email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="jv-input" placeholder="you@store.com" required>
                    @error('email') <span class="jv-error">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-6 jv-field">
                    <label for="phone">Phone</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}"
                           class="jv-input" placeholder="+255 700 000000">
                    @error('phone') <span class="jv-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="jv-field">
                <label for="address">Business address</label>
                <input id="address" type="text" name="address" value="{{ old('address') }}"
                       class="jv-input" placeholder="Street, region, Tanzania">
                @error('address') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="row">
                <div class="col-md-6 jv-field">
                    <label for="tin_number">TIN (Tax Identification Number)</label>
                    <input id="tin_number" type="text" name="tin_number" value="{{ old('tin_number') }}"
                           class="jv-input" placeholder="123-456-789" required>
                    @error('tin_number') <span class="jv-error">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-6 jv-field">
                    <label for="registration_number">Business Registration Number</label>
                    <input id="registration_number" type="text" name="registration_number" value="{{ old('registration_number') }}"
                           class="jv-input" placeholder="BRELA reg. no." required>
                    @error('registration_number') <span class="jv-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="jv-field">
                <label for="business_documents">Business documents <small class="text-muted">(TIN certificate, certificate of incorporation, or business license — PDF/JPG/PNG, up to 5MB each)</small></label>
                <input id="business_documents" type="file" name="business_documents[]" multiple
                       class="jv-input" accept=".pdf,.jpg,.jpeg,.png" required>
                @error('business_documents') <span class="jv-error">{{ $message }}</span> @enderror
                @error('business_documents.*') <span class="jv-error">{{ $message }}</span> @enderror
            </div>

            <div class="row">
                <div class="col-md-6 jv-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" class="jv-input"
                           placeholder="At least 8 characters" required>
                    @error('password') <span class="jv-error">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-6 jv-field">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation"
                           class="jv-input" placeholder="Repeat password" required>
                </div>
            </div>

            <button type="submit" class="jv-btn jv-btn-primary jv-btn-block">Submit application</button>
        </form>

        <p class="jv-auth-foot">
            Already selling? <a href="{{ route('vendor.login') }}">Vendor sign in</a>
        </p>
    </div>
</div>
@endsection
