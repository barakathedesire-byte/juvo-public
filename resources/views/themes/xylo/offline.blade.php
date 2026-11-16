@extends('themes.xylo.layouts.master')

@section('title', 'Offline — Juvo')

@section('content')
<div class="jv-wrap">
    <div class="jv-offline">
        <div class="jv-offline-ico"><i class="fa-solid fa-wifi"></i></div>
        <h1>You are offline</h1>
        <p>
            Juvo needs a connection to load products, prices and your cart.
            Check your network and try again — anything already saved will still be here.
        </p>
        <div class="jv-offline-actions">
            <button type="button" class="jv-btn jv-btn-dark" onclick="window.location.reload()">
                <i class="fa-solid fa-rotate-right me-2"></i>Try again
            </button>
            <a href="{{ route('support.index') }}" class="jv-btn jv-btn-outline">Get help</a>
        </div>
    </div>
</div>
@endsection
