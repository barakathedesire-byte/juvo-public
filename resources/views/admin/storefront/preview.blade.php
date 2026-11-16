@extends('themes.xylo.layouts.master')

@section('title', 'Storefront preview — '.$template->name)

@section('content')
@php $currency = activeCurrency(); @endphp
<div class="jv-page">
    <div class="jv-container">
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong><i class="fas fa-eye me-1"></i> Preview only.</strong>
                Rendering <em>{{ $template->name }}</em> against <em>{{ $vendor->name }}</em>'s catalogue.
            </div>
            <a href="{{ route('admin.storefront.edit', $template->id) }}" class="btn btn-sm btn-outline-secondary">Back to builder</a>
        </div>

        @if(!empty($blocks))
            <div class="jv-page-builder jv-page-blocks jv-pb-cols mb-4">
                @include('themes.xylo.partials.page-blocks.render', ['blocks' => $blocks, 'currency' => $currency])
            </div>
        @else
            <p class="jv-empty">This template has no visible sections yet.</p>
        @endif
    </div>
</div>
@endsection
