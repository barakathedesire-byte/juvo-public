@php
    $s = $block['settings'];
    $h = (int) ($s['height'] ?? 40);
    $bg = $s['bg_color'] ?? null;
@endphp
<div class="jv-blk-spacer" style="height:{{ $h }}px;{{ $bg ? "background-color:{$bg};" : '' }}" aria-hidden="true"></div>
