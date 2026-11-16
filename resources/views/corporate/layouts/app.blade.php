<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f1015">
    <meta name="description" content="@yield('description', 'Juvo is Tanzania’s connected commerce platform — bringing marketplace, logistics and vendor growth together.')">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="{{ url()->current() }}">
    <title>@yield('title', 'Juvo — Commerce, connected.')</title>

    <meta property="og:site_name" content="Juvo">
    <meta property="og:locale" content="en_TZ">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'Juvo — Commerce, connected.')">
    <meta property="og:description" content="@yield('description', 'Juvo is Tanzania’s connected commerce platform — bringing marketplace, logistics and vendor growth together.')">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('images/juvo_logo_3d.png') }}?v={{ filemtime(public_path('images/juvo_logo_3d.png')) ?: time() }}">
    <meta property="og:image:width" content="900">
    <meta property="og:image:height" content="431">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Juvo — Commerce, connected.')">
    <meta name="twitter:description" content="@yield('description', 'Juvo is Tanzania’s connected commerce platform — bringing marketplace, logistics and vendor growth together.')">
    <meta name="twitter:image" content="{{ asset('images/juvo_logo_3d.png') }}?v={{ filemtime(public_path('images/juvo_logo_3d.png')) ?: time() }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/juvo-corporate.css') }}?v={{ filemtime(public_path('css/juvo-corporate.css')) ?: time() }}">

    @yield('meta')

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Organization",
                "name": "Juvo",
                "url": "{{ url('/') }}",
                "logo": "{{ asset('images/juvo_logo_3d.png') }}?v={{ filemtime(public_path('images/juvo_logo_3d.png')) ?: time() }}",
                "description": "Juvo connects vendors, customers and riders on one platform — a marketplace with logistics and vendor growth built in.",
                "address": {
                    "@type": "PostalAddress",
                    "addressLocality": "Dar es Salaam",
                    "addressCountry": "TZ"
                },
                "sameAs": []
            },
            {
                "@type": "WebSite",
                "name": "Juvo",
                "url": "{{ route('xylo.home') }}",
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": "{{ route('search') }}?q={search_term_string}",
                    "query-input": "required name=search_term_string"
                }
            }
        ]
    }
    </script>
</head>
<body class="jvco-body">
    <a class="jvco-skip" href="#main">Skip to content</a>
    <div class="jvco-progress" id="jvcoProgress" aria-hidden="true"></div>

    <header class="jvco-header" id="jvcoHeader">
        <div class="jvco-shell jvco-header-inner">
            <a href="{{ route('company') }}" class="jvco-brand" aria-label="Juvo company home">
                <img src="{{ asset('images/juvo_logo_3d.png') }}?v={{ filemtime(public_path('images/juvo_logo_3d.png')) ?: time() }}" alt="Juvo" width="126" height="60">
            </a>

            <nav class="jvco-nav" id="jvcoNav" aria-label="Company navigation">
                <div class="jvco-nav-item jvco-has-menu">
                    <button class="jvco-nav-link jvco-menu-trigger" type="button" aria-expanded="false" aria-controls="jvcoWhatMenu">
                        What we do
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="jvco-mega" id="jvcoWhatMenu">
                        <div class="jvco-mega-grid">
                            <a href="#marketplace" class="jvco-mega-card">
                                <span class="jvco-mega-icon"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
                                <strong>Marketplace</strong>
                                <small>A storefront for every local vendor.</small>
                            </a>
                            <a href="#logistics" class="jvco-mega-card">
                                <span class="jvco-mega-icon"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span>
                                <strong>Logistics</strong>
                                <small>Dispatch, delivery and live tracking.</small>
                            </a>
                            <a href="#growth" class="jvco-mega-card">
                                <span class="jvco-mega-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                                <strong>Vendor growth</strong>
                                <small>Tools that help sellers scale with confidence.</small>
                            </a>
                        </div>
                        <div class="jvco-mega-foot">
                            <span>One platform. Three engines.</span>
                            <a href="#platform">See how it works <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
                <a href="#about" class="jvco-nav-link">Company</a>
                <a href="#impact" class="jvco-nav-link">Impact</a>
                <a href="#newsroom" class="jvco-nav-link">Newsroom</a>
                <a href="#contact" class="jvco-nav-link">Contact</a>
                <div class="jvco-nav-mobile-cta">
                    <a href="{{ route('xylo.home') }}" class="jvco-btn jvco-btn-ghost">Shop Juvo</a>
                    <a href="{{ route('join.sell') }}" class="jvco-btn jvco-btn-primary">Sell on Juvo</a>
                </div>
            </nav>

            <div class="jvco-header-actions">
                <a href="{{ route('xylo.home') }}" class="jvco-btn jvco-btn-ghost jvco-btn-sm">Shop Juvo</a>
                <a href="{{ route('join.sell') }}" class="jvco-btn jvco-btn-primary jvco-btn-sm">Sell on Juvo</a>
                <button class="jvco-menu-toggle" id="jvcoMenuToggle" type="button" aria-expanded="false" aria-controls="jvcoNav" aria-label="Open menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="jvco-footer" id="contact">
        <div class="jvco-shell">
            <div class="jvco-footer-top">
                <div class="jvco-footer-brand">
                    <a href="{{ route('company') }}" aria-label="Juvo company home">
                        <img src="{{ asset('images/juvo_logo_3d.png') }}?v={{ filemtime(public_path('images/juvo_logo_3d.png')) ?: time() }}" alt="Juvo" width="132" height="64" loading="lazy">
                    </a>
                    <p>Connected commerce for Tanzania. Built to help local vendors sell, riders earn and customers shop with confidence.</p>
                    <div class="jvco-socials">
                        <a href="#" aria-label="Juvo on Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                        <a href="#" aria-label="Juvo on Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
                        <a href="#" aria-label="Juvo on X"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></a>
                        <a href="#" aria-label="Juvo on LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                    </div>
                </div>

                <div class="jvco-footer-col">
                    <h2>Platform</h2>
                    <a href="{{ route('xylo.home') }}">Marketplace</a>
                    <a href="#logistics">Logistics</a>
                    <a href="{{ route('join.sell') }}">Vendor tools</a>
                    <a href="{{ route('join.deliver') }}">Rider network</a>
                </div>

                <div class="jvco-footer-col">
                    <h2>Company</h2>
                    <a href="#about">About Juvo</a>
                    <a href="#impact">Impact</a>
                    <a href="#newsroom">Newsroom</a>
                    <a href="#contact">Contact</a>
                </div>

                <div class="jvco-footer-col">
                    <h2>Get started</h2>
                    <a href="{{ route('customer.register') }}">Create an account</a>
                    <a href="{{ route('join.sell') }}">Sell on Juvo</a>
                    <a href="{{ route('help.index') }}">Help center</a>
                    <a href="{{ route('page.show', 'contact-us') }}">Talk to us</a>
                </div>
            </div>

            <div class="jvco-footer-bottom">
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'Juvo') }}. All rights reserved.</span>
                <div class="jvco-footer-legal">
                    <a href="{{ route('page.show', 'privacy-policy') }}">Privacy</a>
                    <a href="{{ route('page.show', 'terms-conditions') }}">Terms</a>
                    <span>Dar es Salaam, Tanzania</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="{{ asset('js/juvo-corporate.js') }}?v={{ filemtime(public_path('js/juvo-corporate.js')) ?: time() }}" defer></script>
    @yield('scripts')
<script>
(function () {
    var fallback = "{{ asset('images/juvo_logo_3d.png') }}?v={{ filemtime(public_path('images/juvo_logo_3d.png')) ?: time() }}";
    document.addEventListener('error', function (event) {
        var target = event.target;
        if (target && target.tagName === 'IMG' && target.src !== fallback) {
            target.src = fallback;
        }
    }, true);
})();
</script>
</body>
</html>
