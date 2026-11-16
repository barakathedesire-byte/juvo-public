@extends('themes.xylo.layouts.master')

@section('title', 'Homepage Preview — Builder')

@section('css')
    <style>
        .jv-preview-bar {
            background: #1f2937;
            color: #fff;
            padding: 10px 0;
            font-size: 13px;
        }
        .jv-preview-bar .container { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .jv-preview-bar a { color: #ffd8a8; text-decoration: none; }
        .jv-preview-bar a:hover { text-decoration: underline; }
        .jv-preview-empty {
            border: 1px dashed var(--jv-line);
            border-radius: var(--jv-radius-lg);
            padding: 28px;
            text-align: center;
            color: var(--jv-muted);
            margin: 18px 0;
            background: #fff;
        }
    </style>
@endsection

@section('content')
    <div class="jv-preview-bar">
        <div class="container">
            <span><i class="fa-solid fa-eye me-2"></i>Preview — including drafts. This is how the homepage will look.</span>
            <span>
                <a href="{{ route('admin.homepage.index') }}"><i class="fa-solid fa-arrow-left me-1"></i>Back to builder</a>
            </span>
        </div>
    </div>

    <div class="container">
        @php $currency = $currency ?? activeCurrency(); @endphp

        @if(($slides ?? collect())->isEmpty())
            <div class="jv-preview-empty">No slides to show.</div>
        @else
            @include('themes.xylo.partials.home.hero', ['slides' => $slides, 'homeSettings' => $homeSettings])
        @endif

        @if(($tiles ?? collect())->isEmpty())
            <div class="jv-preview-empty">No collection tiles to show.</div>
        @else
            @include('themes.xylo.partials.home.tiles', ['tiles' => $tiles])
        @endif

        @forelse($sections as $section)
            @if($section->type === 'promo_grid')
                @include('themes.xylo.partials.home.promo', ['section' => $section])
            @else
                @include('themes.xylo.partials.home.section', ['section' => $section, 'currency' => $currency])
            @endif
        @empty
            <div class="jv-preview-empty">No sections to show.</div>
        @endforelse

        @include('themes.xylo.partials.home.trust')
    </div>
@endsection
