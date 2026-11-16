{{--
    Configurable homepage section (products, collections, promos, pictures,
    announcements or slides). Expects: $section (HomepageSection with
    ->resolved) and $currency.
--}}
@php
    $settings = $section->settings ?? [];
    $type = $section->type;
    $isImage = $type === 'image';
    $isAnnouncement = $type === 'announcement';
    $isSlides = $type === 'slides';
    $isGallery = $type === 'gallery';
    $isBannersOnly = $isSlides || $isGallery;

    $products = $section->resolved['products'] ?? collect();
    $rows = $section->resolved['rows'] ?? [];
    $collections = $section->resolved['collections'] ?? [];
    $isRows = ($section->layout ?? 'carousel') === 'rows';
    $isCollections = in_array($section->type, ['collection_carousel', 'collection_grid'], true) || count($collections) > 0;
    $collectionLayout = $section->type === 'collection_grid' ? 'grid' : 'carousel';
    $seeAll = match ($section->source) {
        'daily_deals' => route('shop.index', ['collection' => 'deals']),
        'new_arrivals' => route('shop.index', ['collection' => 'new_arrivals']),
        'featured', 'sponsored' => route('shop.index', ['collection' => 'featured']),
        'category' => $section->category ? route('category.show', $section->category->slug) : route('shop.index'),
        'collection' => route('shop.index', ['collection' => $settings['collection'] ?? '']),
        default => route('shop.index'),
    };
    $countdown = $settings['countdown'] ?? null;
    $banners = $section->resolved['banners'] ?? [];

    $bgColor = $settings['bg_color'] ?? null;
    $bgColor2 = $settings['bg_color_2'] ?? null;
    $textColor = $settings['text_color'] ?? null;
    $accentColor = $settings['accent_color'] ?? null;

    $cardEnabled = (bool) ($settings['card_enabled'] ?? false);
    if (! array_key_exists('card_enabled', $settings) && ($bgColor || $textColor)) {
        $cardEnabled = true;
    }

    $edgesOption = $settings['card_edges'] ?? 'lg';
    $shapeOption = $settings['card_shape'] ?? 'rounded';
    $bgStyleOption = $settings['bg_style'] ?? 'solid';

    $edges = in_array($edgesOption, ['none', 'sm', 'md', 'lg', 'pill'], true) ? $edgesOption : 'lg';
    $shape = in_array($shapeOption, ['rect', 'rounded', 'pill'], true) ? $shapeOption : 'rounded';
    $bgStyle = in_array($bgStyleOption, ['solid', 'gradient', 'split'], true) ? $bgStyleOption : 'solid';

    $cardClasses = $cardEnabled
        ? ' jv-section-card jv-section-card--edges-'.$edges.' jv-section-card--shape-'.$shape
        : '';

    $cardStyles = [];
    if ($cardEnabled) {
        if ($bgColor) {
            $cardStyles[] = '--jv-card-bg:'.e($bgColor);
        }
        if ($textColor) {
            $cardStyles[] = '--jv-card-fg:'.e($textColor);
        }
        if ($accentColor) {
            $cardStyles[] = '--jv-card-accent:'.e($accentColor);
        }

        $second = $bgColor2 ?: $bgColor;

        if ($bgColor && $bgStyle === 'gradient') {
            $cardStyles[] = 'background-color:'.e($bgColor);
            $cardStyles[] = 'background-image:linear-gradient(135deg,'.e($bgColor).','.e($second).')';
        } elseif ($bgColor && $bgStyle === 'split') {
            $cardStyles[] = 'background-image:linear-gradient(90deg,'.e($bgColor).' 0 55%,'.e($second).' 55% 100%)';
        } elseif ($bgColor) {
            $cardStyles[] = 'background-color:'.e($bgColor);
        }
    }
    $cardStyle = implode(';', $cardStyles);

    $image = $section->resolved['image'] ?? null;
    $announcement = $section->resolved['announcement'] ?? [];

    $hasContent = match (true) {
        $isImage => (bool) $image,
        $isAnnouncement => (bool) ($section->title || $section->subtitle || ($announcement['body'] ?? null) || ($announcement['cta_label'] ?? null)),
        $isBannersOnly => count($banners) > 0,
        $isRows => count($rows) > 0,
        $isCollections => count($collections) > 0,
        default => $products->count() > 0,
    };

    if (! $isImage && ! $isAnnouncement && ! $isBannersOnly) {
        $hasContent = $hasContent || count($banners) > 0;
    }

    $showHead = $isBannersOnly
        ? (bool) ($section->title || $section->subtitle || $section->icon)
        : ((! $isRows || $section->title) && ! $isImage && ! $isAnnouncement);
    $showSeeAll = ! $isRows && ! $isCollections && ! $isBannersOnly;
@endphp
@if($hasContent)
<section class="jv-section{{ $cardClasses }}{{ $isRows ? ' jv-product-rows' : '' }}{{ $isBannersOnly ? ' jv-section-media' : '' }}" aria-label="{{ $section->title ?: 'Homepage section' }}" @if($cardStyle) style="{{ $cardStyle }}" @endif>
    @if($isImage)
        <figure class="jv-section-figure{{ ($settings['rounded'] ?? true) ? ' jv-section-figure--rounded' : '' }}">
            @if(! empty($settings['link']))<a href="{{ $settings['link'] }}">@endif
                <img src="{{ $image }}" alt="{{ ($settings['alt'] ?? '') ?: (($settings['caption'] ?? '') ?: ($section->title ?: 'Picture')) }}" loading="lazy">
            @if(! empty($settings['link']))</a>@endif
            @if(! empty($settings['caption']))<figcaption>{{ $settings['caption'] }}</figcaption>@endif
        </figure>
    @elseif($isAnnouncement)
        @php
            $toneOption = $announcement['tone'] ?? 'info';
            $tone = in_array($toneOption, ['info', 'success', 'warning', 'promo'], true) ? $toneOption : 'info';
            $toneIcon = ['info' => 'fa-circle-info', 'success' => 'fa-circle-check', 'warning' => 'fa-triangle-exclamation', 'promo' => 'fa-bullhorn'][$tone];
        @endphp
        <div class="jv-announcement jv-announcement--{{ $tone }}">
            <span class="jv-announcement-icon"><i class="fa-solid {{ $toneIcon }}"></i></span>
            <div class="jv-announcement-copy">
                @if($section->title)<h2 class="jv-announcement-title">{{ $section->title }}</h2>@endif
                @if($section->subtitle)<p class="jv-announcement-sub">{{ $section->subtitle }}</p>@endif
                @if(! empty($announcement['body']))<p class="jv-announcement-body">{{ $announcement['body'] }}</p>@endif
            </div>
            @if(! empty($announcement['cta_label']))
                <a class="jv-announcement-cta" href="{{ ($announcement['cta_url'] ?? '') ?: route('shop.index') }}">{{ $announcement['cta_label'] }}</a>
            @endif
        </div>
    @else
        @if($showHead)
            <div class="jv-section-head">
                <div class="jv-section-heading">
                    <h2 class="jv-section-title">
                        @if(!empty($section->icon))<i class="{{ $section->icon }} me-2" style="color:var(--jv-card-accent, var(--jv-orange));"></i>@endif
                        {{ $section->title }}
                    </h2>
                    @if(!empty($section->subtitle))<p class="jv-section-sub">{{ $section->subtitle }}</p>@endif
                </div>
                <div class="d-flex align-items-center gap-3">
                    @if($countdown && ! $isBannersOnly)
                        <span class="jv-chip" data-jv-countdown="{{ $countdown }}" aria-live="polite">
                            <i class="fa-regular fa-clock me-1"></i><span class="jv-countdown-text">--:--:--</span>
                        </span>
                    @endif
                    @if($showSeeAll)
                        <a href="{{ $seeAll }}" class="jv-seeall">See all →</a>
                    @endif
                </div>
            </div>
        @endif

        @if($isSlides)
            <div class="jv-hero-slider jv-section-slides" data-autoplay="1" data-speed="5000" data-arrows="1" data-dots="1">
                @foreach($banners as $banner)
                    @php
                        $slideStyles = [];
                        if (! empty($banner['image'])) {
                            $slideStyles[] = 'background-image:linear-gradient(90deg,rgba(0,0,0,.55),rgba(0,0,0,.12)),url(&quot;'.e($banner['image']).'&quot;)';
                        } elseif (! empty($banner['gradient_from']) && ! empty($banner['gradient_to'])) {
                            $slideStyles[] = 'background-image:linear-gradient(135deg,'.e($banner['gradient_from']).','.e($banner['gradient_to']).')';
                        } elseif (! empty($banner['bg_color'])) {
                            $slideStyles[] = 'background-image:none';
                            $slideStyles[] = 'background-color:'.e($banner['bg_color']);
                        }
                        if (! empty($banner['text_color'])) {
                            $slideStyles[] = 'color:'.e($banner['text_color']);
                        }
                        $slideTag = ! empty($banner['url']) ? 'a' : 'div';
                    @endphp
                    <div class="jv-hero-slide">
                        <{{ $slideTag }} @if($slideTag === 'a') href="{{ $banner['url'] }}" @endif
                            class="jv-hero-slide-inner jv-hero-align-center jv-section-slide"
                            @if($slideStyles) style="{{ implode(';', $slideStyles) }}" @endif>
                            <div class="jv-hero-copy">
                                @if(! empty($banner['title']))<h2 class="jv-hero-title">{{ $banner['title'] }}</h2>@endif
                                @if(! empty($banner['subtitle']))<p class="jv-hero-sub">{{ $banner['subtitle'] }}</p>@endif
                            </div>
                        </{{ $slideTag }}>
                    </div>
                @endforeach
            </div>
        @else
            @if(count($banners))
                <div class="jv-banners">
                    @foreach($banners as $banner)
                        @php
                            $bstyle = [];
                            if (! empty($banner['bg_color'])) { $bstyle[] = 'background-color:'.e($banner['bg_color']); }
                            if (! empty($banner['gradient_from']) && ! empty($banner['gradient_to'])) {
                                $bstyle[] = 'background-image:linear-gradient(135deg,'.e($banner['gradient_from']).','.e($banner['gradient_to']).')';
                            }
                            if (! empty($banner['text_color'])) { $bstyle[] = 'color:'.e($banner['text_color']); }
                            $bclass = 'jv-banner jv-banner--size-'.($banner['size'] ?? 'md')
                                .' jv-banner--edges-'.($banner['edges'] ?? 'md')
                                .' jv-banner--shape-'.($banner['shape'] ?? 'rounded')
                                .' jv-banner--effect-'.($banner['effect'] ?? 'none');
                            $btag = ! empty($banner['url']) ? 'a' : 'div';
                        @endphp
                        <{{ $btag }} @if($btag === 'a') href="{{ $banner['url'] }}" @endif class="{{ $bclass }}" @if($bstyle) style="{{ implode(';', $bstyle) }}" @endif>
                            @if(! empty($banner['image']))
                                <img src="{{ $banner['image'] }}" alt="{{ $banner['title'] ?? '' }}" loading="lazy">
                            @endif
                            @if(! empty($banner['title']) || ! empty($banner['subtitle']))
                                <div class="jv-banner-body">
                                    @if(! empty($banner['title']))<span class="jv-banner-title">{{ $banner['title'] }}</span>@endif
                                    @if(! empty($banner['subtitle']))<span class="jv-banner-sub">{{ $banner['subtitle'] }}</span>@endif
                                </div>
                            @endif
                        </{{ $btag }}>
                    @endforeach
                </div>
            @endif

            @unless($isGallery)
                @if($isCollections)
                    <div class="{{ $collectionLayout === 'grid' ? 'jv-collection-grid' : 'jv-product-slider jv-rail' }}" @if($collectionLayout !== 'grid') data-arrows="1" @endif>
                        @foreach($collections as $collection)
                            @include('themes.xylo.partials.home.collection-card', ['collection' => $collection])
                        @endforeach
                    </div>
                @elseif($isRows)
                    @foreach($rows as $row)
                        @php
                            $rowProducts = $row['products'] ?? collect();
                            $cols = $row['columns'] ?? ['desktop' => 5, 'tablet' => 3, 'mobile' => 2];
                            $rowLayout = $row['layout'] ?? 'carousel';
                            $rowEmptyState = $row['empty_state'] ?? null;
                        @endphp
                        @if($rowProducts->count() || $rowEmptyState)
                            <div class="jv-product-row">
                                @if(!empty($row['title']) || (!empty($row['see_all']) && !empty($row['see_all_url'])))
                                    <div class="jv-section-head jv-row-head">
                                        <div class="jv-section-heading">
                                            @if(!empty($row['title']))<h3 class="jv-section-title">{{ $row['title'] }}</h3>@endif
                                            @if(!empty($row['subtitle']))<p class="jv-section-sub">{{ $row['subtitle'] }}</p>@endif
                                        </div>
                                        @if(!empty($row['see_all']) && !empty($row['see_all_url']))
                                            <a href="{{ $row['see_all_url'] }}" class="jv-seeall">See all →</a>
                                        @endif
                                    </div>
                                @endif

                                @if($rowProducts->isEmpty())
                                    <p class="jv-row-empty">{{ $rowEmptyState }}</p>
                                @elseif($rowLayout === 'grid')
                                    <div class="jv-row-grid"
                                         style="--jv-cols-desktop:{{ (int) $cols['desktop'] }};--jv-cols-tablet:{{ (int) $cols['tablet'] }};--jv-cols-mobile:{{ (int) $cols['mobile'] }};">
                                        @foreach($rowProducts as $product)
                                            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                                        @endforeach
                                    </div>
                                @else
                                    <div class="jv-product-slider jv-rail"
                                         data-arrows="1"
                                         data-slides-desktop="{{ (int) $cols['desktop'] }}"
                                         data-slides-tablet="{{ (int) $cols['tablet'] }}"
                                         data-slides-mobile="{{ (int) $cols['mobile'] }}">
                                        @foreach($rowProducts as $product)
                                            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                @elseif(($section->layout ?? 'carousel') === 'grid')
                    <div class="jv-grid">
                        @foreach($products as $product)
                            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                        @endforeach
                    </div>
                @else
                    <div class="jv-product-slider jv-rail" data-arrows="1">
                        @foreach($products as $product)
                            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => $currency])
                        @endforeach
                    </div>
                @endif
            @endunless
        @endif
    @endif
</section>
@endif
