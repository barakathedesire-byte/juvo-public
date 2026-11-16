<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Juvo') . ' — Online Marketplace')</title>

    @include('themes.xylo.partials.pwa-meta')

    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/css/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/slick/css/slick.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/slick/css/slick-theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/toastr/css/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/juvo.css') }}?v={{ @filemtime(public_path('css/juvo.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/storefront-mobile.css') }}?v={{ @filemtime(public_path('css/storefront-mobile.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/page-builder.css') }}?v={{ @filemtime(public_path('css/page-builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/juvo-app.css') }}?v={{ @filemtime(public_path('css/juvo-app.css')) }}">
    @yield('meta')
    @yield('css')
</head>
<body>
    <div id="jv-progress" role="progressbar" aria-hidden="true"></div>
    @include('themes.xylo.layouts.header')

    <main class="jv-main">
        @yield('content')
    </main>

    @include('themes.xylo.layouts.footer')

    <script src="{{ asset('vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/slick/js/slick.min.js') }}"></script>
    <script src="{{ asset('vendor/toastr/js/toastr.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if ($('.jv-hero-slider').length) {
                $('.jv-hero-slider').each(function () {
                    var $el = $(this);
                    var autoplay = $el.data('autoplay') === 1 || $el.data('autoplay') === '1';
                    $el.slick({
                        slidesToShow: 1, slidesToScroll: 1,
                        autoplay: autoplay && !reduceMotion,
                        autoplaySpeed: parseInt($el.data('speed'), 10) || 5000,
                        fade: true, speed: reduceMotion ? 0 : 500, cssEase: 'linear',
                        dots: true, arrows: true, pauseOnHover: true, pauseOnFocus: true,
                        adaptiveHeight: false, touchThreshold: 12,
                    });
                });
            }

            if ($('.jv-banner-slider').length) {
                $('.jv-banner-slider').slick({
                    slidesToShow: 1, slidesToScroll: 1, autoplay: !reduceMotion, fade: true,
                    speed: 500, cssEase: 'linear', autoplaySpeed: 5000, dots: true, arrows: false,
                });
            }
            if ($('.jv-product-slider').length) {
                $('.jv-product-slider').each(function () {
                    var $el = $(this);
                    var desktop = parseInt($el.data('slides-desktop'), 10) || 5;
                    var tablet = parseInt($el.data('slides-tablet'), 10) || 3;
                    var mobile = parseInt($el.data('slides-mobile'), 10) || 2;
                    var arrows = $el.data('arrows') === 0 || $el.data('arrows') === '0' ? false : true;
                    $el.slick({
                        slidesToShow: desktop, slidesToScroll: 1, infinite: true,
                        autoplay: !reduceMotion, autoplaySpeed: 4000, arrows: arrows, dots: false,
                        responsive: [
                            { breakpoint: 1100, settings: { slidesToShow: Math.max(1, tablet + 1) } },
                            { breakpoint: 820, settings: { slidesToShow: tablet } },
                            { breakpoint: 560, settings: { slidesToShow: mobile } },
                        ]
                    });
                });
            }
            if ($('.jv-welcome-slider').length) {
                $('.jv-welcome-slider').slick({
                    slidesToShow: 3, slidesToScroll: 1, infinite: true, autoplay: !reduceMotion,
                    autoplaySpeed: 3500, arrows: true, dots: true,
                    responsive: [
                        { breakpoint: 820, settings: { slidesToShow: 2 } },
                        { breakpoint: 620, settings: { slidesToShow: 1 } },
                    ],
                });
            }

            /* Keyboard support for the collection tiles */
            $(document).on('keydown', '.jv-hm-tile', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = $(this).attr('href');
                }
            });
        });
    </script>
    <script>
        /* Live search: detects the words being typed, predicts the intent and
           suggests matching queries, categories, brands and products. */
        $(document).ready(function () {
            var $input = $('#jv-search-input');
            var $box = $('#jv-search-suggest');
            if (!$input.length || !$box.length) { return; }

            var suggestUrl = '{{ url('/search-suggestions') }}';
            var searchUrl = '{{ url('/search') }}';
            var eventUrl = '{{ route('recommendations.event') }}';
            var eventToken = $('meta[name="csrf-token"]').attr('content') || '';

            var term = '';
            var timer = null;
            var xhr = null;
            var items = [];
            var active = -1;
            var minChars = 2;

            function esc(s) {
                return $('<div>').text(s == null ? '' : s).html();
            }

            /* Wrap the matched part of a label so the shopper sees why it matched. */
            function highlight(label, q) {
                label = label == null ? '' : String(label);
                if (!q) { return esc(label); }
                var idx = label.toLowerCase().indexOf(q.toLowerCase());
                if (idx < 0) { return esc(label); }
                return esc(label.slice(0, idx)) +
                    '<mark>' + esc(label.slice(idx, idx + q.length)) + '</mark>' +
                    esc(label.slice(idx + q.length));
            }

            function section(title, html) {
                if (!html) { return ''; }
                return '<div class="jv-sugg-sec"><div class="jv-sugg-head">' + esc(title) + '</div>' + html + '</div>';
            }

            function register(html, href, attrs) {
                items.push({ href: href, attrs: attrs || {} });
                return '<a class="jv-sugg-item ' + (attrs && attrs.cls ? attrs.cls : '') +
                    '" href="' + esc(href) + '"' + (attrs && attrs.data ? attrs.data : '') + '>' + html + '</a>';
            }

            function render(data) {
                items = [];
                active = -1;
                var html = '';

                if (data.did_you_mean) {
                    html += '<a class="jv-sugg-dym" href="' + esc(searchUrl + '?q=' + encodeURIComponent(data.did_you_mean)) + '">' +
                        '<i class="fa fa-wand-magic-sparkles"></i> Did you mean <b>' + esc(data.did_you_mean) + '</b>?</a>';
                    items.push({ href: searchUrl + '?q=' + encodeURIComponent(data.did_you_mean), attrs: { repeat: true } });
                }

                var qHtml = '';
                (data.queries || []).forEach(function (row) {
                    var t = typeof row === 'string' ? row : row.term;
                    qHtml += register('<i class="fa fa-magnifying-glass jv-sugg-ico"></i><span>' + highlight(t, term) + '</span>', searchUrl + '?q=' + encodeURIComponent(t));
                });
                html += section('Suggestions', qHtml);

                var cHtml = '';
                (data.categories || []).forEach(function (c) {
                    cHtml += register('<i class="fa fa-list jv-sugg-ico"></i><span>' + highlight(c.name, term) + '</span>' +
                        (c.count ? '<span class="jv-sugg-count">' + esc(c.count) + '</span>' : '') + '<i class="fa fa-angle-right jv-sugg-go"></i>',
                        '/category/' + encodeURIComponent(c.slug));
                });
                html += section('Categories', cHtml);

                var bHtml = '';
                (data.brands || []).forEach(function (b) {
                    bHtml += register('<i class="fa fa-tag jv-sugg-ico"></i><span>' + highlight(b.name, term) + '</span>' +
                        '<i class="fa fa-angle-right jv-sugg-go"></i>', searchUrl + '?q=' + encodeURIComponent(b.name));
                });
                html += section('Brands', bHtml);

                var pHtml = '';
                (data.products || []).forEach(function (p) {
                    pHtml += register('<img src="' + esc(p.thumbnail || '') + '" alt="" loading="lazy">' +
                        '<span>' + highlight(p.name, term) + '</span>',
                        '/product/' + encodeURIComponent(p.slug),
                        { data: ' data-suggest-id="' + esc(p.id) + '"' });
                });
                html += section('Products', pHtml);

                var tHtml = '';
                (data.trending || []).forEach(function (t) {
                    var term2 = typeof t === 'string' ? t : t.term;
                    tHtml += register('<i class="fa fa-arrow-trend-up jv-sugg-ico"></i><span>' + esc(term2) + '</span>', searchUrl + '?q=' + encodeURIComponent(term2));
                });
                html += section('Trending searches', tHtml);

                $box.html(html);
                $box.toggleClass('d-none', $box.children().length === 0);
            }

            function close() {
                $box.addClass('d-none');
                items = [];
                active = -1;
            }

            function setActive(next) {
                var $all = $box.find('a');
                if (!$all.length) { return; }
                active = (next + $all.length) % $all.length;
                $all.removeClass('is-active').attr('aria-selected', 'false');
                var $active = $all.eq(active);
                $active.addClass('is-active').attr('aria-selected', 'true');
                var node = $active.get(0);
                if (node && node.scrollIntoView) { node.scrollIntoView({ block: 'nearest' }); }
            }

            function fetch(q) {
                if (xhr && xhr.readyState !== 4) { xhr.abort(); }
                xhr = $.ajax({
                    url: suggestUrl,
                    type: 'GET',
                    data: { q: q, limit: 6 },
                    success: function (data) {
                        term = q;
                        render(data);
                    }
                });
            }

            function queue(q) {
                clearTimeout(timer);
                timer = setTimeout(function () { fetch(q); }, 160);
            }

            $input.on('input', function () {
                var q = $.trim($(this).val());
                term = q;
                if (q.length >= minChars) {
                    queue(q);
                } else if (q.length === 0) {
                    fetch('');
                } else {
                    close();
                }
            });

            $input.on('focus', function () {
                if ($.trim($(this).val()) === '') { fetch(''); }
            });

            $input.on('keydown', function (e) {
                if ($box.hasClass('d-none') || !$box.find('a').length) { return; }
                if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
                else if (e.key === 'Enter' && active >= 0) {
                    e.preventDefault();
                    var node = $box.find('a').get(active);
                    if (node) { window.location.href = node.href; }
                } else if (e.key === 'Escape') { close(); }
            });

            $box.on('mousemove', 'a', function () {
                $box.find('a').removeClass('is-active');
                $(this).addClass('is-active');
                active = $(this).index();
            });

            /* Record autocomplete selection so query-quality analytics see it. */
            $box.on('click', 'a[data-suggest-id]', function () {
                try {
                    var id = $(this).data('suggest-id');
                    var body = JSON.stringify({ events: [{
                        type: 'click',
                        surface: 'search_autocomplete',
                        product_id: id,
                        algorithm_key: 'search_ranking',
                        meta: { query: term }
                    }] });
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(eventUrl, new Blob([body], { type: 'application/json' }));
                    } else {
                        $.ajax({ url: eventUrl, type: 'POST', contentType: 'application/json', data: body, headers: { 'X-CSRF-TOKEN': eventToken } });
                    }
                } catch (e) {}
            });

            $(document).on('click', function (event) {
                if (!$(event.target).closest('#jv-search-input, #jv-search-suggest').length) {
                    close();
                }
            });
        });
    </script>
    <script>
        /* Flash sale countdown (resets every 24h from midnight) */
        $(document).ready(function () {
            var $t = $('[data-jv-countdown]');
            if (!$t.length) { return; }
            function tick() {
                var now = new Date();
                var end = new Date(now);
                end.setHours(23, 59, 59, 0);
                var diff = Math.max(0, Math.floor((end - now) / 1000));
                var h = String(Math.floor(diff / 3600)).padStart(2, '0');
                var m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
                var s = String(diff % 60).padStart(2, '0');
                $t.find('[data-h]').text(h);
                $t.find('[data-m]').text(m);
                $t.find('[data-s]').text(s);
            }
            tick();
            setInterval(tick, 1000);
        });
    </script>
    <script>
        /* Add to cart used across listing pages */
        function jvAddToCart(productId, variantId, qty) {
            fetch("{{ route('cart.add') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json",
                },
                body: JSON.stringify({ product_id: productId, variant_id: variantId || null, quantity: qty || 1 })
            })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(function (data) {
                if (data && data.cart) {
                    var total = Object.values(data.cart).reduce(function (sum, item) { return sum + item.quantity; }, 0);
                    var el = document.getElementById('cart-count');
                    if (el) {
                        el.textContent = total;
                        el.classList.toggle('d-none', total < 1);
                    }
                }
                toastr.success((data && data.message) || 'Added to cart', 'Juvo', {
                    closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4000
                });
            })
            .catch(function (e) { toastr.error('Could not add to cart'); console.error(e); });
        }

        /* Wishlist toggle used across product cards */
        function jvToggleWishlist(btn) {
            var id = btn.getAttribute('data-product-id');
            fetch("{{ route('customer.wishlist.toggle') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json",
                },
                body: JSON.stringify({ product_id: id })
            })
            .then(function (r) {
                if (r.status === 401) { window.location.href = "{{ route('customer.login') }}"; return null; }
                return r.json();
            })
            .then(function (data) {
                if (!data) { return; }
                var icon = btn.querySelector('i');
                if (data.status === 'added') {
                    if (icon) { icon.className = 'fa-solid fa-heart'; }
                    btn.classList.add('is-active');
                    toastr.success(data.message || 'Added to wishlist');
                } else if (data.status === 'removed') {
                    if (icon) { icon.className = 'fa-regular fa-heart'; }
                    btn.classList.remove('is-active');
                    toastr.info(data.message || 'Removed from wishlist');
                }
            })
            .catch(function (e) { console.error(e); });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.wishlist-btn');
            if (btn) { e.preventDefault(); jvToggleWishlist(btn); }
        });

        document.addEventListener('click', function (e) {
            var quick = e.target.closest('.jv-quick-add');
            if (!quick) { return; }
            e.preventDefault();
            e.stopPropagation();
            quick.classList.add('is-loading');
            jvAddToCart(quick.getAttribute('data-product-id'));
            setTimeout(function () { quick.classList.remove('is-loading'); }, 600);
        });
    </script>
    <script>
        /* ---- Non-static page loading feedback ---- */
        (function () {
            var bar = document.getElementById('jv-progress');
            if (!bar) { return; }
            var timer = null, active = false;

            function start() {
                active = true;
                bar.classList.add('is-active');
                bar.style.width = '8%';
                clearInterval(timer);
                var w = 8;
                timer = setInterval(function () {
                    w = Math.min(92, w + Math.max(1, (92 - w) * 0.12));
                    bar.style.width = w + '%';
                }, 220);
            }

            function done() {
                if (!active) { return; }
                active = false;
                clearInterval(timer);
                bar.style.width = '100%';
                setTimeout(function () {
                    bar.classList.remove('is-active');
                    setTimeout(function () { bar.style.width = '0%'; }, 300);
                }, 220);
            }

            document.addEventListener('click', function (e) {
                var a = e.target.closest('a[href]');
                if (!a || a.target === '_blank' || a.hasAttribute('download')) { return; }
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) { return; }
                var href = a.getAttribute('href');
                if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) { return; }
                if (a.host && a.host !== window.location.host) { return; }
                start();
            });
            document.addEventListener('submit', function () { start(); }, true);
            window.addEventListener('pageshow', done);
            window.addEventListener('load', done);
            window.addEventListener('beforeunload', function () { if (active) { bar.classList.add('is-active'); } });
        })();

        /* ---- Kill the media skeleton once its image resolves (no pop-in, no shift) ---- */
        (function () {
            function settle(img) {
                var media = img.closest('.jv-product-media');
                if (media) { media.classList.add('is-loaded'); }
            }
            function scan(root) {
                (root || document).querySelectorAll('.jv-product-media img').forEach(function (img) {
                    if (img.complete && img.naturalWidth > 0) { settle(img); return; }
                    img.addEventListener('load', function () { settle(img); }, { once: true });
                    img.addEventListener('error', function () { settle(img); }, { once: true });
                });
            }
            document.addEventListener('DOMContentLoaded', function () { scan(document); });
            window.jvSettleImages = scan;
        })();

        /* ---- Reusable skeleton grid for AJAX-driven listings ---- */
        window.jvSkeletonCards = function (count) {
            var out = '';
            for (var i = 0; i < (count || 8); i++) {
                out += '<div class="jv-product jv-product-skeleton" aria-hidden="true">' +
                    '<div class="jv-product-media"></div>' +
                    '<div class="jv-product-body">' +
                        '<div class="jv-skel jv-skel-line w-85"></div>' +
                        '<div class="jv-skel jv-skel-line w-60"></div>' +
                        '<div class="jv-skel jv-skel-line w-40"></div>' +
                    '</div></div>';
            }
            return out;
        };
    </script>
    <script src="{{ asset('js/storefront-mobile.js') }}?v={{ @filemtime(public_path('js/storefront-mobile.js')) }}"></script>
    <script src="{{ asset('js/page-blocks.js') }}?v={{ @filemtime(public_path('js/page-blocks.js')) }}"></script>
    <script src="{{ asset('js/pwa.js') }}?v={{ @filemtime(public_path('js/pwa.js')) }}"></script>
    <script src="{{ asset('js/notifications.js') }}?v={{ @filemtime(public_path('js/notifications.js')) }}"></script>
    @yield('js')
<script>
(function () {
    var fallback = "{{ asset('images/juvo_logo_3d.png') }}";
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
