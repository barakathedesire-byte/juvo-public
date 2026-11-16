@php
    $s = $block['settings'];
    $rows = $block['data']['rows'] ?? [];
    $bgColor = $s['bg_color'] ?? null;
    $textColor = $s['text_color'] ?? null;
    $cardStyle = $bgColor ? '--jv-card-bg:'.e($bgColor).';'.($textColor ? '--jv-card-fg:'.e($textColor).';' : '') : null;
@endphp
@if(count($rows))
<section class="jv-section jv-product-rows{{ $bgColor ? ' jv-section-card' : '' }}" aria-label="{{ $s['title'] ?? 'Product sections' }}" @if($cardStyle) style="{{ $cardStyle }}" @endif>
    @if(!empty($s['title']) || !empty($s['subtitle']))
        <div class="jv-section-head">
            <div class="jv-section-heading">
                @if(!empty($s['title']))<h2 class="jv-section-title">{{ $s['title'] }}</h2>@endif
                @if(!empty($s['subtitle']))<p class="jv-section-sub">{{ $s['subtitle'] }}</p>@endif
            </div>
        </div>
    @endif

    @foreach($rows as $row)
        @php
            $products = $row['products'] ?? collect();
            $cols = $row['columns'] ?? ['desktop' => 5, 'tablet' => 3, 'mobile' => 2];
            $isGrid = ($row['layout'] ?? 'carousel') === 'grid';
            $seeAllUrl = $row['see_all_url'] ?? null;
        @endphp
        <div class="jv-product-row">
            @if(!empty($row['title']) || (!empty($row['see_all']) && ($seeAllUrl || !empty($row['title']))))
                <div class="jv-section-head jv-row-head">
                    <div class="jv-section-heading">
                        @if(!empty($row['title']))<h3 class="jv-section-title">{{ $row['title'] }}</h3>@endif
                    </div>
                    @if(!empty($row['see_all']) && $seeAllUrl)
                        <a href="{{ $seeAllUrl }}" class="jv-seeall">See all →</a>
                    @endif
                </div>
            @endif

            @if($products->count())
                @if($isGrid)
                    <div class="jv-row-grid"
                         style="--jv-cols-desktop:{{ (int) $cols['desktop'] }};--jv-cols-tablet:{{ (int) $cols['tablet'] }};--jv-cols-mobile:{{ (int) $cols['mobile'] }};">
                        @foreach($products as $product)
                            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                        @endforeach
                    </div>
                @else
                    <div class="jv-product-slider jv-rail"
                         data-arrows="{{ !empty($row['arrows']) ? '1' : '0' }}"
                         data-dots="0"
                         data-autoplay="0"
                         data-slides-desktop="{{ (int) $cols['desktop'] }}"
                         data-slides-tablet="{{ (int) $cols['tablet'] }}"
                         data-slides-mobile="{{ (int) $cols['mobile'] }}">
                        @foreach($products as $product)
                            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                        @endforeach
                    </div>
                @endif
            @elseif(!empty($row['empty_state']))
                <p class="jv-row-empty">{{ $row['empty_state'] }}</p>
            @endif
        </div>
    @endforeach
</section>
@endif
