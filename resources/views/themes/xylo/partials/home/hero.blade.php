{{--
    Homepage hero carousel.
    Expects: $slides (Collection of objects: title, subtitle, description, cta_label,
             cta_url, image, mobile_image, badge, text_align), $homeSettings
--}}
@php
    $imgUrl = function (?string $p) {
        if (! $p) { return null; }
        return \Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p);
    };
@endphp
@if(($slides ?? collect())->count())
<section class="jv-hm-hero jv-section" aria-label="Promotions">
    <div class="jv-hero-slider"
         data-autoplay="{{ !empty($homeSettings['autoplay']) ? '1' : '0' }}"
         data-speed="{{ (int) ($homeSettings['autoplay_speed'] ?? 5000) }}">
        @foreach($slides as $slide)
            @php
                $desktop = $imgUrl($slide->image ?? null);
                $mobile = $imgUrl($slide->mobile_image ?? null) ?: $desktop;
                $align = in_array(($slide->text_align ?? 'left'), ['left','center','right']) ? $slide->text_align : 'left';

                $height = in_array(($slide->height ?? 'medium'), ['small','medium','large','auto'], true) ? $slide->height : 'medium';
                $height = $height ?: 'medium';
                $overlay = in_array(($slide->overlay ?? 'medium'), ['none','light','medium','dark'], true) ? $slide->overlay : 'medium';
                $overlay = $overlay ?: 'medium';

                $style = [];
                if (!empty($slide->bg_color)) {
                    $end = !empty($slide->bg_color_2) ? $slide->bg_color_2 : $slide->bg_color;
                    $style[] = 'background: linear-gradient(120deg, '.$slide->bg_color.' 0%, '.$end.' 100%)';
                }
                if (!empty($slide->text_color)) {
                    $style[] = '--jv-hero-text: '.$slide->text_color;
                }
                if (!empty($slide->title_size)) {
                    $style[] = '--jv-hero-title-size: '.(int) $slide->title_size.'px';
                }
            @endphp
            <div class="jv-hero-slide">
                <div class="jv-hero-slide-inner jv-hero-align-{{ $align }} jv-hero-h-{{ $height }} jv-hero-ov-{{ $overlay }}"
                     @if($style) style="{{ implode('; ', $style) }}" @endif>
                    <div class="jv-hero-copy">
                        @if(!empty($slide->badge))<span class="jv-hero-badge">{{ $slide->badge }}</span>@endif
                        @if(!empty($slide->title))<h2 class="jv-hero-title">{{ $slide->title }}</h2>@endif
                        @if(!empty($slide->subtitle))<p class="jv-hero-sub">{{ $slide->subtitle }}</p>@endif
                        @if(!empty($slide->description))<p class="jv-hero-desc">{{ $slide->description }}</p>@endif
                        @if(!empty($slide->cta_label))
                            <a href="{{ $slide->cta_url ?: route('shop.index') }}" class="jv-btn jv-btn-primary jv-hero-cta">{{ $slide->cta_label }}</a>
                        @endif
                    </div>
                    @if($desktop)
                        <img class="jv-hero-img jv-hero-img-desktop" src="{{ $desktop }}" alt="{{ $slide->title ?? 'Promotion' }}">
                    @endif
                    @if($mobile)
                        <img class="jv-hero-img jv-hero-img-mobile" src="{{ $mobile }}" alt="{{ $slide->title ?? 'Promotion' }}">
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
