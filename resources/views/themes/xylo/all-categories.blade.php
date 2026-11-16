@extends('themes.xylo.layouts.master')

@section('title', 'Browse All Categories')

@section('css')
<style>
    .jv-ac-head { padding: 22px 0 6px; }
    .jv-ac-head h1 { font-size: 26px; font-weight: 800; color: var(--jv-ink); margin: 0; }
    .jv-ac-head p { color: var(--jv-muted, #6b7280); margin: 6px 0 0; font-size: 14px; }
    .jv-ac-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin: 18px 0 10px; }
    .jv-ac-card { background: #fff; border: 1px solid #ededed; border-radius: 16px; padding: 16px 16px 14px; transition: box-shadow .15s ease, transform .15s ease; }
    .jv-ac-card:hover { box-shadow: 0 10px 26px rgba(0,0,0,.08); transform: translateY(-2px); }
    .jv-ac-top { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .jv-ac-thumb { width: 64px; height: 64px; border-radius: 16px; background: var(--jv-orange-soft, #fff3e6); display: flex; align-items: center; justify-content: center; flex: 0 0 64px; overflow: hidden; }
    .jv-ac-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .jv-ac-thumb i { color: var(--jv-orange); font-size: 30px; }
    .jv-ac-name { font-weight: 800; font-size: 16px; color: var(--jv-ink); text-decoration: none; }
    .jv-ac-name:hover { color: var(--jv-orange); }
    .jv-ac-count { font-size: 12px; color: #9ca3af; }
    .jv-ac-subs { display: flex; flex-wrap: wrap; gap: 8px; }
    .jv-ac-sub { font-size: 13px; color: #374151; background: #f6f6f7; border-radius: 20px; padding: 6px 12px; text-decoration: none; }
    .jv-ac-sub:hover { background: var(--jv-orange-soft, #fff3e6); color: var(--jv-orange); }
    .jv-ac-more { display: inline-block; margin-top: 12px; font-size: 13px; font-weight: 600; color: var(--jv-orange); text-decoration: none; }
    .jv-ac-empty { text-align: center; color: #6b7280; padding: 60px 0; }
    @media (max-width: 576px) { .jv-ac-grid { grid-template-columns: 1fr 1fr; gap: 12px; } .jv-ac-thumb { width: 46px; height: 46px; flex-basis: 46px; } .jv-ac-head h1 { font-size: 22px; } }
</style>
@endsection

@section('content')
<div class="jv-page">
    <div class="jv-container">
        <nav class="jv-breadcrumb">
            <a href="{{ route('xylo.home') }}">{{ __('store.category.home') ?? 'Home' }}</a>
            <i class="fa fa-angle-right"></i>
            <span>All Categories</span>
        </nav>

        @include('themes.xylo.partials.page-region', ['key' => 'categories'])

        <div class="jv-ac-head">
            <h1>Browse All Categories</h1>
            <p>Pick a department below to explore everything we have in stock.</p>
        </div>

        @if($categories->count())
            <div class="jv-ac-grid">
                @foreach($categories as $cat)
                    <div class="jv-ac-card">
                        <div class="jv-ac-top">
                            <span class="jv-ac-thumb">
                                <i class="{{ $cat->icon ?: category_icon($cat->slug) }}"></i>
                            </span>
                            <div>
                                <a class="jv-ac-name" href="{{ route('category.show', $cat->slug) }}">{{ $cat->translation->name ?? $cat->slug }}</a>
                                <div class="jv-ac-count">{{ $cat->children->count() }} {{ Str::plural('sub-category', $cat->children->count()) }}</div>
                            </div>
                        </div>

                        @if($cat->children->count())
                            <div class="jv-ac-subs">
                                @foreach($cat->children as $child)
                                    <a class="jv-ac-sub" href="{{ route('category.show', $child->slug) }}">{{ $child->translation->name ?? $child->slug }}</a>
                                @endforeach
                            </div>
                            <a class="jv-ac-more" href="{{ route('category.show', $cat->slug) }}">View {{ $cat->translation->name ?? $cat->slug }} →</a>
                        @else
                            <div class="jv-ac-subs">
                                <a class="jv-ac-sub" href="{{ route('category.show', $cat->slug) }}">Shop {{ $cat->translation->name ?? $cat->slug }}</a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="jv-ac-empty">No categories available yet.</p>
        @endif
    </div>
</div>
@endsection
