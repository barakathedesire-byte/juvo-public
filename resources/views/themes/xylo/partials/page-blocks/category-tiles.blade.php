@php
    $s = $block['settings'];
    $tiles = $block['data']['tiles'] ?? collect();
    $cols = (int) ($s['columns'] ?? 6);
    $imgUrl = fn ($p) => $p ? (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p)) : null;
@endphp
@if($tiles->count())
<section class="jv-section">
    @if(!empty($s['title']) || !empty($s['subtitle']))
        <div class="jv-section-head">
            <div class="jv-section-heading">
                @if(!empty($s['title']))<h2 class="jv-section-title">{{ $s['title'] }}</h2>@endif
                @if(!empty($s['subtitle']))<p class="jv-section-sub">{{ $s['subtitle'] }}</p>@endif
            </div>
        </div>
    @endif
    <div class="jv-blk-tiles" data-cols="{{ $cols }}">
        @foreach($tiles as $tile)
            <a href="{{ $tile['url'] }}" class="jv-blk-tile">
                <span class="jv-blk-tile-media">
                    @if($imgUrl($tile['image'] ?? null))
                        <img src="{{ $imgUrl($tile['image']) }}" alt="{{ $tile['title'] }}" loading="lazy">
                    @else
                        <span class="jv-blk-tile-fallback"><i class="fa-solid fa-shapes"></i></span>
                    @endif
                </span>
                <span class="jv-blk-tile-label">{{ $tile['title'] }}</span>
            </a>
        @endforeach
    </div>
</section>
@endif
