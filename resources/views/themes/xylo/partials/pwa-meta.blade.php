{{--
    PWA + cross-platform meta.

    Makes the storefront installable and well-behaved as a home-screen app on
    Android/Chrome, iOS/Safari, Windows/Edge-Chrome and desktop Linux, using a
    single manifest plus the Apple- and Microsoft-specific tags those
    platforms require.
--}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

{{-- Classic + modern favicons --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/favicon-16.png') }}">

{{-- iOS: Safari ignores the manifest for icons and needs these --}}
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Juvo">
<link rel="mask-icon" href="{{ asset('icons/icon-512.png') }}" color="#f68b1e">

{{-- Android / Chrome --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Juvo">

{{-- Windows / Edge: tile + taskbar pinning --}}
<meta name="msapplication-TileColor" content="#f68b1e">
<meta name="msapplication-TileImage" content="{{ asset('icons/icon-192.png') }}">
<meta name="msapplication-config" content="none">

{{-- Desktop install affordance on Chrome/Edge for Linux, Windows and macOS --}}
<meta name="theme-color" content="#f68b1e" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#12131a" media="(prefers-color-scheme: dark)">
<meta name="color-scheme" content="light dark">

{{-- Standalone window: keep the layout full-bleed on notched devices --}}
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<meta name="format-detection" content="telephone=no">
