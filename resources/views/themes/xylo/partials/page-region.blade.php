{{--
    Renders the editable content region for a system page (shop/category/product/cart/checkout).
    Expects: $key
--}}
@php
    $__regionBlocks = app(\App\Services\Pages\PageRenderer::class)->region($key ?? '');
@endphp
@if(count($__regionBlocks))
    <div class="jv-region jv-region-{{ $key }}">
        @include('themes.xylo.partials.page-blocks.render', ['blocks' => $__regionBlocks, 'currency' => activeCurrency()])
    </div>
@endif
