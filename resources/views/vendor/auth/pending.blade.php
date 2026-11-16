@extends('themes.xylo.layouts.auth')
@section('title', 'Application received — Juvo')

@section('content')
<div class="jv-auth-wrap">
    <div class="jv-auth-card" style="text-align:center;">
        <div class="jv-auth-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" height="46"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <span class="jv-wordmark" style="display:none;">Ju<span>vo</span></span>
        </div>
        <div style="font-size:54px;color:var(--jv-green);margin:8px 0 4px;"><i class="fa-solid fa-circle-check"></i></div>
        <h2>Application received</h2>
        <p class="jv-sub">
            Thanks for applying to sell on Juvo. Our team is reviewing your store —
            you'll be able to sign in as soon as it's approved.
        </p>
        <a href="{{ route('vendor.login') }}" class="jv-btn jv-btn-dark jv-btn-block">Back to vendor sign in</a>
        <p class="jv-auth-foot">Questions? <a href="{{ storefront_url('/') }}">Contact Juvo support</a></p>
    </div>
</div>
@endsection
