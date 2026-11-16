{{--
    PWA meta for the vendor portal.

    Gives the vendor app its own installable identity ("Juvo Vendor") so it is
    installed on the phone as a separate app from the shopper storefront.
--}}
<link rel="manifest" href="{{ asset('vendor-manifest.webmanifest') }}">

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/favicon-16.png') }}">

<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Juvo Vendor">
<link rel="mask-icon" href="{{ asset('icons/icon-512.png') }}" color="#f68b1e">

<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Juvo Vendor">

<meta name="msapplication-TileColor" content="#f68b1e">
<meta name="msapplication-TileImage" content="{{ asset('icons/icon-192.png') }}">
<meta name="msapplication-config" content="none">

<meta name="theme-color" content="#f68b1e" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#12131a" media="(prefers-color-scheme: dark)">

<meta name="format-detection" content="telephone=no">
