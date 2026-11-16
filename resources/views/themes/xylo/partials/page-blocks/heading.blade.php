@php
    $s = $block['settings'];
    $level = in_array(($s['level'] ?? 'h2'), ['h1', 'h2', 'h3', 'h4'], true) ? $s['level'] : 'h2';
    $style = $s['color'] ? "color:{$s['color']};" : '';
@endphp
<section class="jv-section jv-blk-heading jv-blk-text-{{ $s['align'] ?? 'left' }}">
    @if($level === 'h1')
        <h1 class="jv-section-title" style="{{ $style }}">{{ $s['text'] ?? '' }}</h1>
    @else
        <{{ $level }} class="jv-section-title" style="{{ $style }}">{{ $s['text'] ?? '' }}</{{ $level }}>
    @endif
</section>
