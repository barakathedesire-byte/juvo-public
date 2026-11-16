@php
    $s = $block['settings'];
    $img = $s['image'] ?? null;
    $imgUrl = $img ? (\Illuminate\Support\Str::startsWith($img, ['http://', 'https://']) ? $img : \Illuminate\Support\Facades\Storage::url($img)) : null;
@endphp
<section class="jv-section">
    <div class="jv-blk-vendor">
        <div class="jv-blk-vendor-copy">
            @if(!empty($s['heading']))<h2 class="jv-blk-vendor-title">{{ $s['heading'] }}</h2>@endif
            @if(!empty($s['subtitle']))<p class="jv-blk-vendor-sub">{{ $s['subtitle'] }}</p>@endif
            @if(!empty($s['cta_label']))
                <a href="{{ $s['cta_url'] ?: '/vendor/register' }}" class="jv-btn jv-btn-primary">{{ $s['cta_label'] }}</a>
            @endif
        </div>
        @if($imgUrl)
            <div class="jv-blk-vendor-media"><img src="{{ $imgUrl }}" alt="{{ $s['heading'] ?? '' }}" loading="lazy"></div>
        @endif
    </div>
</section>
