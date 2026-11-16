@extends('themes.xylo.layouts.master')
@php
    $pageTitle = $page->seo_title ?: ($translation->title ?? $page->slug);
    $metaDesc = $page->seo_description ?: \Illuminate\Support\Str::limit(strip_tags($translation->content ?? ''), 160);
    $ogImage = $page->seo_image
        ? (\Illuminate\Support\Str::startsWith($page->seo_image, ['http://', 'https://']) ? $page->seo_image : \Illuminate\Support\Facades\Storage::url($page->seo_image))
        : ($translation && $translation->image_url ? \Illuminate\Support\Facades\Storage::url($translation->image_url) : null);
@endphp
@section('title', $pageTitle)

@section('meta')
    @if($metaDesc)<meta name="description" content="{{ $metaDesc }}">@endif
    <meta property="og:title" content="{{ $pageTitle }}">
    @if($metaDesc)<meta property="og:description" content="{{ $metaDesc }}">@endif
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @if($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
@endsection

@section('content')
@if(!empty($blocks))
    <div class="container jv-page-builder jv-page-blocks jv-pb-cols" data-jv-page>
        <nav class="jv-breadcrumb" style="padding-top:16px;">
            <a href="{{ url('/') }}">{{ __('store.product_detail.home') }}</a>
            <i class="fa fa-angle-right"></i>
            <span>{{ $translation->title ?? $pageTitle }}</span>
        </nav>
        @include('themes.xylo.partials.page-blocks.render', ['blocks' => $blocks, 'currency' => activeCurrency()])
    </div>
@else
    <div class="container" style="padding-top:24px;">
        <nav class="jv-breadcrumb">
            <a href="{{ url('/') }}">{{ __('store.product_detail.home') }}</a>
            <i class="fa fa-angle-right"></i>
            <span>{{ $translation->title }}</span>
        </nav>

        <article class="jv-section" style="max-width:900px;margin:0 auto;">
            <h1 style="font-size:28px;font-weight:800;margin-bottom:6px;">{{ $translation->title }}</h1>
            @if($translation->image_url)
                <img src="{{ Storage::url($translation->image_url) }}" alt="{{ $translation->title }}"
                     style="max-width:100%;border-radius:12px;margin:14px 0;">
            @endif
            <div class="jv-page-content" style="line-height:1.7;color:#374151;">
                {!! \App\Support\Html\HtmlSanitizer::clean($translation->content) !!}
            </div>
        </article>
    </div>
@endif
@endsection
