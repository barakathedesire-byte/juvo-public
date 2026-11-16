@php
    $ads = ad_slot($key ?? 'homepage_strip', $limit ?? 1);
@endphp
@once
    <style>
        .jv-ad-slot{display:flex;gap:16px;flex-wrap:wrap}
        .jv-ad-card{position:relative;flex:1 1 320px;display:flex;gap:14px;align-items:center;background:#fff;border:1px solid #eee;border-radius:14px;padding:14px;text-decoration:none;color:inherit;box-shadow:0 2px 10px rgba(0,0,0,.04);transition:box-shadow .2s}
        .jv-ad-card:hover{box-shadow:0 6px 18px rgba(0,0,0,.10)}
        .jv-ad-card img{width:120px;height:80px;object-fit:cover;border-radius:10px;flex-shrink:0}
        .jv-ad-body{min-width:0}
        .jv-ad-body h3{font-size:16px;margin:4px 0 2px}
        .jv-ad-body p{font-size:13px;color:#666;margin:0}
        .jv-ad-tag{display:inline-block;font-size:10px;text-transform:uppercase;letter-spacing:.5px;background:#fff3e6;color:#f68b1e;border-radius:6px;padding:1px 6px}
    </style>
@endonce
@if($ads->count())
    <section class="jv-section">
        <div class="jv-ad-slot">
            @foreach($ads as $ad)
                @php $product = $ad->product; @endphp
                <a href="{{ route('ads.click', $ad->id) }}" class="jv-ad-card" target="_blank" rel="noopener sponsored">
                    @if($ad->creative_image)
                        <img src="{{ \Illuminate\Support\Str::startsWith($ad->creative_image, ['http://','https://']) ? $ad->creative_image : asset('storage/'.$ad->creative_image) }}"
                             alt="{{ $ad->creative_headline ?? $ad->title }}">
                    @elseif($product?->thumbnail)
                        <img src="{{ Storage::url(optional($product->translation)->image ?? $product->thumbnail->image) }}"
                             alt="{{ $ad->creative_headline ?? $ad->title }}">
                    @endif
                    <div class="jv-ad-body">
                        <span class="jv-ad-tag">Sponsored</span>
                        <h3>{{ $ad->creative_headline ?? $ad->title }}</h3>
                        @if($ad->creative_body)
                            <p>{{ \Illuminate\Support\Str::limit($ad->creative_body, 120) }}</p>
                        @endif
                    </div>
                </a>
                <img src="{{ route('ads.impression', $ad->id) }}" width="1" height="1" alt="" style="position:absolute;left:-9999px;">
            @endforeach
        </div>
    </section>
@endif
