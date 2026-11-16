{{--
    Promotional feature section (large + small cards).
    Expects: $section (HomepageSection with ->resolved['cards'])
--}}
@php
    $cards = $section->resolved['cards'] ?? [];
    $imgUrl = function (?string $p) {
        if (! $p) { return null; }
        return \Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p);
    };
@endphp
@if(count($cards))
<section class="jv-section jv-hm-promos" aria-label="{{ $section->title ?? 'Promotions' }}">
    @if(!empty($section->title))
        <div class="jv-section-head">
            <h2 class="jv-section-title">{{ $section->title }}</h2>
        </div>
    @endif
    <div class="jv-hm-promo-grid">
        @foreach($cards as $card)
            @php $img = $imgUrl($card['image'] ?? null); @endphp
            <a class="jv-hm-promo-card jv-hm-promo-{{ $card['size'] }} jv-hm-theme-{{ $card['theme'] }}"
               href="{{ $card['url'] }}">
                @if($img)
                    <img class="jv-hm-promo-img" src="{{ $img }}" alt="{{ $card['title'] }}" loading="lazy">
                @endif
                <div class="jv-hm-promo-body">
                    @if(!empty($card['badge']))<span class="jv-hm-promo-badge">{{ $card['badge'] }}</span>@endif
                    <h3 class="jv-hm-promo-title">{{ $card['title'] }}</h3>
                    @if(!empty($card['subtitle']))<p class="jv-hm-promo-sub">{{ $card['subtitle'] }}</p>@endif
                    <span class="jv-hm-promo-cta">{{ $card['cta_label'] ?? 'Shop now' }} <i class="fa-solid fa-arrow-right-long"></i></span>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif
