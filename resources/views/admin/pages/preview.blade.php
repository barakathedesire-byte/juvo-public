@extends('admin.layouts.admin')

@section('title', 'Preview — '.$previewTitle)

@section('css')
    <link rel="stylesheet" href="{{ asset('vendor/slick/css/slick.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/slick/css/slick-theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/page-builder.css') }}?v={{ @filemtime(public_path('css/page-builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/juvo.css') }}?v={{ @filemtime(public_path('css/juvo.css')) }}">
    <style>
        .jvpb-preview-wrap { background: #eef0f3; padding: 16px; border-radius: 14px; }
        .jvpb-preview-frame { width: 100%; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; transition: max-width .2s ease; }
        .jvpb-preview-frame.device-desktop { max-width: 100%; }
        .jvpb-preview-frame.device-tablet { max-width: 834px; }
        .jvpb-preview-frame.device-mobile { max-width: 390px; }
    </style>
@endsection

@section('content')
    <div class="mt-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h4 class="mb-0">Preview: {{ $previewTitle }}</h4>
            <p class="text-muted mb-0">
                Draft content — not visible on the storefront.
                @if($page->status)<span class="badge bg-success ms-1">Published</span>@else<span class="badge bg-secondary ms-1">Draft</span>@endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-secondary active" data-device="desktop"><i class="bi bi-display"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-device="tablet"><i class="bi bi-tablet"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-device="mobile"><i class="bi bi-phone"></i></button>
            </div>
            <a href="{{ route('admin.pages.editor', $page->id) }}" class="btn btn-sm btn-primary">Back to editor</a>
        </div>
    </div>

    <div class="jvpb-preview-wrap mt-3">
        <div class="jvpb-preview-frame device-desktop" id="jvpbPreviewFrame">
            @if(!empty($blocks))
                <div class="container jv-pb-cols" style="padding:20px 16px;">
                    @include('themes.xylo.partials.page-blocks.render', ['blocks' => $blocks, 'currency' => activeCurrency()])
                </div>
            @elseif($content)
                <div class="container" style="padding:24px 16px;">
                    <h1 class="mb-3">{{ $previewTitle }}</h1>
                    <div class="jv-page-content">{!! $content !!}</div>
                </div>
            @else
                <div class="p-5 text-center text-muted">This page has no sections yet. Add components in the editor.</div>
            @endif
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('vendor/slick/js/slick.min.js') }}"></script>
    <script src="{{ asset('js/page-blocks.js') }}?v={{ @filemtime(public_path('js/page-blocks.js')) }}"></script>
    <script>
        $(function () {
            $('.jv-hero-slider').each(function () {
                var $el = $(this);
                $el.slick({
                    slidesToShow: 1, slidesToScroll: 1,
                    autoplay: $el.data('autoplay') === 1 || $el.data('autoplay') === '1',
                    autoplaySpeed: parseInt($el.data('speed'), 10) || 5000,
                    fade: true, speed: 400, dots: true, arrows: true,
                });
            });
            $('.jv-product-slider').slick({
                slidesToShow: 5, slidesToScroll: 1, infinite: true, arrows: true, dots: false,
                responsive: [
                    { breakpoint: 1100, settings: { slidesToShow: 4 } },
                    { breakpoint: 820, settings: { slidesToShow: 3 } },
                    { breakpoint: 620, settings: { slidesToShow: 2 } },
                    { breakpoint: 420, settings: { slidesToShow: 1 } },
                ],
            });
        });

        document.querySelectorAll('[data-device]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('[data-device]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const frame = document.getElementById('jvpbPreviewFrame');
                frame.className = 'jvpb-preview-frame device-' + btn.dataset.device;
            });
        });
    </script>
@endsection
