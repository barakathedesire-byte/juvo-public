@php
    $s = $block['settings'];
    $categories = $block['data']['categories'] ?? collect();
    $cols = (int) ($s['columns'] ?? 4);
    $imgUrl = fn ($p) => $p ? (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p)) : null;
@endphp
@if($categories->count())
<section class="jv-section">
    @if(!empty($s['title']) || !empty($s['subtitle']))
        <div class="jv-section-head">
            <div class="jv-section-heading">
                @if(!empty($s['title']))<h2 class="jv-section-title">{{ $s['title'] }}</h2>@endif
                @if(!empty($s['subtitle']))<p class="jv-section-sub">{{ $s['subtitle'] }}</p>@endif
            </div>
        </div>
    @endif
    <div class="jv-blk-catgrid" style="--cols:{{ $cols }}">
        @foreach($categories as $cat)
            <a href="{{ $cat['url'] }}" class="jv-blk-catcard">
                <span class="jv-blk-catcard-media">
                    @if($imgUrl($cat['image'] ?? null))
                        <img src="{{ $imgUrl($cat['image']) }}" alt="{{ $cat['title'] }}" loading="lazy">
                    @else
                        <span class="jv-blk-tile-fallback"><i class="fa-solid fa-layer-group"></i></span>
                    @endif
                </span>
                <span class="jv-blk-catcard-body">
                    <span class="jv-blk-catcard-title">{{ $cat['title'] }}</span>
                    @if(!empty($cat['count']))
                        <span class="jv-blk-catcard-count">{{ $cat['count'] }} products</span>
                    @endif
                </span>
            </a>
        @endforeach
    </div>
</section>
@endif
