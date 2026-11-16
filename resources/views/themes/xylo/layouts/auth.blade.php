<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f68b1e">
    <title>@yield('title', config('app.name', 'Juvo'))</title>
    <link rel="icon" type="image/png" href="{{ asset('images/juvo_logo_3d.png') }}">
    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/toastr/css/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/juvo.css') }}?v={{ @filemtime(public_path('css/juvo.css')) }}">
    @yield('css')
</head>
<body>
    @include('themes.xylo.layouts.header')
    @yield('content')
    <script src="{{ asset('vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/toastr/js/toastr.min.js') }}"></script>
    <script>
        @if (session('error')) toastr.error(@json(session('error'))); @endif
        @if (session('success')) toastr.success(@json(session('success'))); @endif
    </script>
    @yield('js')
</body>
</html>
