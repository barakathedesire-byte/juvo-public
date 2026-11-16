{{--
    Promotional discovery strip — circular tiles.
    Expects: $tiles (Collection of objects: title, subtitle, image, url)
--}}
@php
    $fallbackIcons = [
        'Just Landed' => 'fa-solid fa-bolt',
        'Last Chance Deals' => 'fa-solid fa-tags',
        'Little Wonders' => 'fa-solid fa-baby',
        'Hidden Gems' => 'fa-solid fa-gem',
    ];
    $imgUrl = function (?string $p) {
        if (! $p) { return null; }
        return \Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p);
    };
@endphp
@if(($tiles ?? collect())->count())
<section class="jv-section" aria-label="Featured collections">
    <div class="jv-section-head">
        <h2 class="jv-section-title">Explore Collections</h2>
    </div>
    <div class="jv-hm-tiles" role="list">
        @foreach($tiles as $tile)
            @php
                $img = $imgUrl($tile->image ?? null);
                $tileClasses = 'jv-hm-tile jv-hm-tile--'.($tile->size ?: 'md').' jv-hm-tile--'.($tile->shape ?: 'circle');
                $tileStyles = [];
                if (! empty($tile->bg_color)) {
                    $tileStyles[] = 'background-color:'.e($tile->bg_color);
                }
                if (! empty($tile->text_color)) {
                    $tileStyles[] = 'color:'.e($tile->text_color);
                }
            @endphp
            <a class="{{ $tileClasses }}" role="listitem" href="{{ $tile->url }}" tabindex="0" @if($tileStyles) style="{{ implode(';', $tileStyles) }}" @endif>
                <span class="jv-hm-tile-ring">
                    @if($img)
                        <img src="{{ $img }}" alt="{{ $tile->title }}" loading="lazy">
                    @else
                        <span class="jv-hm-tile-fallback"><i class="{{ $fallbackIcons[$tile->title] ?? 'fa-solid fa-star' }}"></i></span>
                    @endif
                </span>
                <span class="jv-hm-tile-title">{{ $tile->title }}</span>
                @if(!empty($tile->subtitle))<span class="jv-hm-tile-sub">{{ $tile->subtitle }}</span>@endif
            </a>
        @endforeach
    </div>
</section>
@endif
