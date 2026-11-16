{{--
    Renders page-builder blocks.
    Expects: $blocks (resolved array from PageRenderer::render), optional $currency
--}}
@php
    $currency = $currency ?? activeCurrency();
@endphp
@foreach($blocks as $block)
    @if($block['type'])
        @include('themes.xylo.partials.page-blocks.'.str_replace('_', '-', $block['type']), ['block' => $block, 'currency' => $currency])
    @endif
@endforeach
