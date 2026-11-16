@php $s = $block['settings']; @endphp
@if(!empty($s['text']))
<div class="jv-blk-notice jv-blk-notice-{{ $s['tone'] ?? 'info' }}">
    <i class="fa-solid {{ ['info' => 'fa-circle-info', 'success' => 'fa-circle-check', 'warning' => 'fa-triangle-exclamation'][$s['tone'] ?? 'info'] ?? 'fa-circle-info' }}"></i>
    <span>{{ $s['text'] }}</span>
</div>
@endif
