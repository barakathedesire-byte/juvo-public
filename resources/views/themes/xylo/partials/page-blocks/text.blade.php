@php
    $s = $block['settings'];
    $max = ['sm' => 'jv-blk-w-sm', 'md' => 'jv-blk-w-md', 'lg' => 'jv-blk-w-lg', 'full' => 'jv-blk-w-full'][$s['max_width'] ?? 'md'] ?? 'jv-blk-w-md';
@endphp
<section class="jv-section jv-blk-text {{ $max }} jv-blk-text-{{ $s['align'] ?? 'left' }}">
    @if(!empty($s['heading']))<h2 class="jv-section-title">{{ $s['heading'] }}</h2>@endif
    <div class="jv-blk-richtext">{!! $s['body'] ?? '' !!}</div>
</section>
