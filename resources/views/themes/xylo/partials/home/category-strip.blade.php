{{--
    Top categories strip — a horizontally scrollable/swipeable rail of image tiles.
    Expects: $categories (top-level Category models with ->translation), $homeSettings
--}}
@php
    $imgUrl = function (?string $p) {
        if (! $p) { return null; }
        return \Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : asset('storage/' . ltrim($p, '/'));
    };
    $fallbackIcons = [
        'phones-tablets' => 'fa-solid fa-mobile-screen',
        'electronics' => 'fa-solid fa-tv',
        'computing' => 'fa-solid fa-laptop',
        'home-office' => 'fa-solid fa-couch',
        'appliances' => 'fa-solid fa-blender',
        'fashion' => 'fa-solid fa-shirt',
        'health-beauty' => 'fa-solid fa-spray-can-sparkles',
        'groceries' => 'fa-solid fa-basket-shopping',
        'baby' => 'fa-solid fa-baby',
        'sporting' => 'fa-solid fa-dumbbell',
        'gaming' => 'fa-solid fa-gamepad',
        'books' => 'fa-solid fa-book',
    ];
@endphp
@if(($categories ?? collect())->count() && ($homeSettings['show_category_strip'] ?? true))
<section class="jv-section" aria-label="Shop by category">
    <div class="jv-section-head">
        <h2 class="jv-section-title"><i class="fa-solid fa-grid-2 me-2" style="color:var(--jv-orange);"></i>Shop by Category</h2>
        <a href="{{ route('categories.index') }}" class="jv-seeall">See all →</a>
    </div>

    <div class="jv-cat-wrap">
        <button type="button" class="jv-cat-nav jv-cat-prev" aria-label="Scroll categories left">
            <i class="fa-solid fa-chevron-left"></i>
        </button>

        <div class="jv-cat-rail" data-cat-rail role="list">
            @foreach($categories as $cat)
                @php $img = $imgUrl(optional($cat->translation)->image_url); @endphp
                <a class="jv-cat-tile" role="listitem" href="{{ route('category.show', $cat->slug) }}" tabindex="0">
                    <span class="jv-cat-ring">
                        @if($img)
                            <img src="{{ $img }}" alt="{{ $cat->translation->name ?? $cat->slug }}" loading="lazy">
                        @else
                            <span class="jv-cat-fallback"><i class="{{ $fallbackIcons[$cat->slug] ?? 'fa-solid fa-box-open' }}"></i></span>
                        @endif
                    </span>
                    <span class="jv-cat-title">{{ $cat->translation->name ?? $cat->slug }}</span>
                </a>
            @endforeach
        </div>

        <button type="button" class="jv-cat-nav jv-cat-next" aria-label="Scroll categories right">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>

    <script>
    (function () {
        var rail = document.querySelector('[data-cat-rail]');
        if (!rail) return;
        var prev = document.querySelector('.jv-cat-prev');
        var next = document.querySelector('.jv-cat-next');
        function step() { return Math.max(160, Math.round(rail.clientWidth * 0.7)); }
        function update() {
            var max = rail.scrollWidth - rail.clientWidth - 2;
            if (prev) prev.classList.toggle('is-disabled', rail.scrollLeft <= 2);
            if (next) next.classList.toggle('is-disabled', rail.scrollLeft >= max);
        }
        if (prev) prev.addEventListener('click', function () { rail.scrollBy({ left: -step(), behavior: 'smooth' }); });
        if (next) next.addEventListener('click', function () { rail.scrollBy({ left: step(), behavior: 'smooth' }); });
        rail.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    })();
    </script>
</section>
@endif
