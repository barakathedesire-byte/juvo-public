@php
    $s = $block['settings'];
    $color = $s['color'] ?? 'var(--jv-border, #e5e7eb)';
@endphp
<div class="jv-section" aria-hidden="true">
    <hr class="jv-blk-divider" style="border-top-style:{{ $s['style'] ?? 'solid' }};border-top-color:{{ $color }};">
</div>
