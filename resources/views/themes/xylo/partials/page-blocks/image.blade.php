@php
    $s = $block['settings'];
    $imgUrl = fn ($p) => $p ? (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p)) : null;
    $desktop = $imgUrl($s['image'] ?? null);
    $mobile = $imgUrl($s['mobile_image'] ?? null) ?: $desktop;
@endphp
@if($desktop)
<section class="jv-section jv-blk-text-{{ $s['align'] ?? 'center' }}">
    <figure class="jv-blk-image {{ !empty($s['rounded']) ? 'jv-blk-rounded' : '' }}">
        @if(!empty($s['link']))<a href="{{ $s['link'] }}">@endif
            <img class="jv-blk-img-desktop" src="{{ $desktop }}" alt="{{ $s['alt'] ?? ($s['caption'] ?? '') }}" loading="lazy">
            <img class="jv-blk-img-mobile" src="{{ $mobile }}" alt="{{ $s['alt'] ?? ($s['caption'] ?? '') }}" loading="lazy">
        @if(!empty($s['link']))</a>@endif
        @if(!empty($s['caption']))<figcaption>{{ $s['caption'] }}</figcaption>@endif
    </figure>
</section>
@endif
