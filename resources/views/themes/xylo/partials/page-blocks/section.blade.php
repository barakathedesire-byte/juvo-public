@php
    $s = $block['settings'] ?? [];
    $max = ['sm' => 'jv-blk-w-sm', 'md' => 'jv-blk-w-md', 'lg' => 'jv-blk-w-lg', 'full' => 'jv-blk-w-full'][$s['max_width'] ?? 'lg'] ?? 'jv-blk-w-lg';
    $align = $s['align'] ?? 'left';
    $padding = (int) ($s['padding'] ?? 32);
    $imgUrl = fn ($p) => $p ? (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p)) : null;
    $image = $imgUrl($s['image'] ?? null);
    $position = $s['image_position'] ?? 'right';
    $style = 'padding:'.$padding.'px;';
    if (! empty($s['bg_color'])) {
        $style .= 'background-color:'.$s['bg_color'].';';
    }
    if (! empty($s['text_color'])) {
        $style .= 'color:'.$s['text_color'].';';
    }
@endphp
<section class="jv-section jv-blk-section {{ $max }} jv-blk-text-{{ $align }}{{ $image ? ' jv-blk-section-has-image jv-blk-section-img-'.$position : '' }}" style="{{ $style }}">
    <div class="jv-blk-section-copy">
        @if(!empty($s['heading']))<h2 class="jv-section-title">{{ $s['heading'] }}</h2>@endif
        @if(!empty($s['subtitle']))<p class="jv-section-sub">{{ $s['subtitle'] }}</p>@endif
        <div class="jv-blk-richtext">{!! $s['body'] ?? '' !!}</div>
    </div>
    @if($image)
        <figure class="jv-blk-section-media">
            <img src="{{ $image }}" alt="{{ $s['heading'] ?? '' }}" loading="lazy">
        </figure>
    @endif
</section>
