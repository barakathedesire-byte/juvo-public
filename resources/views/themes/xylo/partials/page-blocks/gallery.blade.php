@php
    $s = $block['settings'];
    $images = $block['data']['images'] ?? ($s['images'] ?? []);
    $cols = (int) ($s['columns'] ?? 3);
    $imgUrl = fn ($p) => $p ? (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p)) : null;
@endphp
@if(count($images))
<section class="jv-section">
    @if(!empty($s['title']))<h2 class="jv-section-title">{{ $s['title'] }}</h2>@endif
    <div class="jv-blk-gallery" style="--cols:{{ $cols }}">
        @foreach($images as $img)
            <a href="{{ $imgUrl($img) }}" class="jv-blk-gallery-item" target="_blank" rel="noopener">
                <img src="{{ $imgUrl($img) }}" alt="" loading="lazy">
            </a>
        @endforeach
    </div>
</section>
@endif
