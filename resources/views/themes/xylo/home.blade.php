@extends('themes.xylo.layouts.master')
@section('title', 'Juvo — Online Marketplace')

@section('css')
@php
    $hs = $homeSettings ?? [];
    $bg = $hs['bg_color'] ?? '';
    $text = $hs['text_color'] ?? '';
    $accent = $hs['accent_color'] ?? '';
    $card = $hs['card_color'] ?? '';
    $effects = ! empty($hs['effects']);
    $accentSoft = '';
    if ($accent) {
        $hex = ltrim($accent, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $accentSoft = 'rgba('.hexdec(substr($hex, 0, 2)).','.hexdec(substr($hex, 2, 2)).','.hexdec(substr($hex, 4, 2)).',0.14)';
    }
@endphp
@if($bg || $text || $accent || $card || $effects)
<style>
    :root {
        @if($bg) --jv-bg: {{ $bg }}; @endif
        @if($text) --jv-ink: {{ $text }}; @endif
        @if($accent) --jv-orange: {{ $accent }}; --jv-orange-dark: {{ $accent }}; --jv-orange-soft: {{ $accentSoft }}; @endif
        @if($card) --jv-card-color: {{ $card }}; @endif
    }
    @if($card)
    .jv-product, .jv-section-card, .jv-collection-card { background: var(--jv-card-color); }
    @endif
    @if($hs['effects'] ?? false)
    .jv-home-effects .jv-product,
    .jv-home-effects .jv-collection-card { transition: transform .25s ease, box-shadow .25s ease; }
    .jv-home-effects .jv-product:hover,
    .jv-home-effects .jv-collection-card:hover { transform: translateY(-4px); box-shadow: var(--jv-shadow-lg); }
    .jv-home-effects .jv-core-btn,
    .jv-home-effects .jv-btn-primary { transition: transform .2s ease, box-shadow .2s ease; }
    .jv-home-effects .jv-core-btn:hover,
    .jv-home-effects .jv-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(246,139,30,.32); }
    .jv-home-effects .container { background-image: radial-gradient(1200px 380px at 50% -160px, var(--jv-orange-soft, #fff3e6) 0%, rgba(255,255,255,0) 70%); background-repeat: no-repeat; }
    @media (prefers-reduced-motion: reduce) {
        .jv-home-effects .jv-product, .jv-home-effects .jv-collection-card,
        .jv-home-effects .jv-core-btn, .jv-home-effects .jv-btn-primary { transition: none; }
    }
    @endif
</style>
@endif
@endsection

@section('content')
    @php
        $currency = activeCurrency();
    @endphp

    <div class="container">
        {{-- B. Hero banner carousel --}}
        @include('themes.xylo.partials.home.hero', ['slides' => $slides, 'homeSettings' => $homeSettings])

        {{-- B2. Top categories strip (driven by Homepage Setting "show_category_strip") --}}
        @include('themes.xylo.partials.home.category-strip', ['categories' => $categories, 'currency' => $currency, 'homeSettings' => $homeSettings])

        {{-- C. Promotional discovery strip --}}
        @include('themes.xylo.partials.home.tiles', ['tiles' => $tiles])

        {{-- D + E. Admin-managed homepage sections (promo feature + product discovery) --}}
        @foreach($sections as $section)
            @if($section->type === 'promo_grid')
                @include('themes.xylo.partials.home.promo', ['section' => $section])
            @else
                @include('themes.xylo.partials.home.section', ['section' => $section, 'currency' => $currency])
            @endif
        @endforeach

        {{-- Personalised discovery rail (Recommendation engine) --}}
        @if(($recommended ?? collect())->count())
            @include('themes.xylo.partials.product-rail', [
                'title' => 'Recommended for you',
                'products' => $recommended,
                'currency' => $currency,
                'icon' => 'fa-solid fa-wand-magic-sparkles',
                'surface' => 'home_personalized',
                'seeAllUrl' => route('shop.index'),
            ])
        @endif

        {{-- Sponsored strip --}}
        @include('themes.xylo.partials.ad-slot', ['key' => 'homepage_strip', 'limit' => 2])

        {{-- Recently viewed (client-side) --}}
        <section class="jv-section d-none" id="jv-recent-section">
            <div class="jv-section-head">
                <h2 class="jv-section-title"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--jv-orange);"></i>Recently Viewed</h2>
            </div>
            <div class="jv-recent" id="jv-recent-rail"></div>
        </section>

        {{-- Trust strip --}}
        @include('themes.xylo.partials.home.trust')
    </div>
@endsection

@section('js')
@if($homeSettings['effects'] ?? false)
<script>document.documentElement.classList.add('jv-home-effects');</script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    /* Recently viewed rail */
    var recent = [];
    try { recent = JSON.parse(localStorage.getItem('juvo_recent') || '[]'); } catch (e) {}
    if (recent.length > 0) {
        var section = document.getElementById('jv-recent-section');
        var rail = document.getElementById('jv-recent-rail');
        var curSymbol = document.getElementById('currency-symbol');
        var symbol = curSymbol ? curSymbol.textContent : 'TSh';
        recent.slice(0, 10).forEach(function (item) {
            var div = document.createElement('div');
            div.className = 'jv-product';
            div.innerHTML =
                '<div class="jv-product-media"><a href="' + item.url + '">' +
                    '<img src="' + (item.image || '{{ asset("images/juvo_logo_3d.png") }}') + '" alt="' + item.name + '" loading="lazy">' +
                '</a></div>' +
                '<div class="jv-product-body"><a href="' + item.url + '"><h3 class="jv-product-name">' + item.name + '</h3></a>' +
                '<div class="jv-price-row"><span class="jv-price">' + symbol + Number(item.price || 0).toFixed(2) + '</span></div></div>';
            rail.appendChild(div);
        });
        section.classList.remove('d-none');
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var timers = Array.prototype.slice.call(document.querySelectorAll('[data-jv-countdown]'));
    if (!timers.length) return;
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
        var now = Date.now();
        timers.forEach(function (el) {
            var end = new Date(el.getAttribute('data-jv-countdown')).getTime();
            var text = el.querySelector('.jv-countdown-text');
            var diff = Math.max(0, end - now);
            if (!diff) { if (text) text.textContent = 'Ended'; return; }
            var s = Math.floor(diff / 1000);
            var d = Math.floor(s / 86400); s -= d * 86400;
            var h = Math.floor(s / 3600); s -= h * 3600;
            var m = Math.floor(s / 60); s -= m * 60;
            if (text) text.textContent = (d ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
        });
    }
    tick();
    setInterval(tick, 1000);
});
</script>
@endsection
