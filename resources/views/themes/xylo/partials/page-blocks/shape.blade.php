@php
    $s = $block['settings'] ?? [];
    $shape = $s['shape'] ?? 'circle';
    $color = $s['color'] ?? '#f68b1e';
    $width = (int) ($s['width'] ?? 120);
    $height = (int) ($s['height'] ?? 120);
    $opacity = max(0, min(100, (int) ($s['opacity'] ?? 15))) / 100;
    $align = in_array(($s['align'] ?? 'center'), ['left', 'center', 'right'], true) ? $s['align'] : 'center';
    $radius = match ($shape) {
        'circle' => '50%',
        'rounded' => '18px',
        'line' => '999px',
        'blob' => '60% 40% 55% 45% / 45% 55% 45% 55%',
        default => '50%',
    };
    $height = $shape === 'line' ? max(2, $height) : $height;
    $style = 'width:'.$width.'px;height:'.$height.'px;background-color:'.$color.';opacity:'.$opacity.';border-radius:'.$radius.';';
    $wrapStyle = ! empty($s['bg_color']) ? 'background-color:'.$s['bg_color'].';' : '';
@endphp
<div class="jv-section jv-blk-shape-wrap jv-blk-shape-align-{{ $align }}"@if($wrapStyle) style="{{ $wrapStyle }}"@endif>
    <div class="jv-blk-shape" style="{{ $style }}" aria-hidden="true"></div>
</div>
