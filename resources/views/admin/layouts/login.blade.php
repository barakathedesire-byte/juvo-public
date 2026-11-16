<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Juvo') . ' — Console')</title>
    <meta name="theme-color" content="#f68b1e">
    <link rel="icon" type="image/png" href="{{ asset('images/juvo_logo_3d.png') }}">

    <!-- Fonts -->    <link href="{{ asset('vendor/fonts/nunito.css') }}" rel="stylesheet">

    <!-- Scripts -->
    @if (!App::environment('testing'))
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @endif
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/css/bootstrap-icons.css') }}">

    @yield('css')
</head>
<body>

    @yield('content')

    @yield('js')
</body>
</html>
