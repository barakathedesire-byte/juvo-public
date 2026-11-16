@extends('themes.xylo.layouts.master')

@section('title', $shop->name . ' — Collections')

@section('content')
    <div class="jv-page">
        @include('themes.xylo.partials.store-header', ['active' => 'collections'])

        <div class="jv-container">
            @if(!empty($blocks))
                <div class="jv-page-builder jv-page-blocks jv-pb-cols mb-4">
                    @include('themes.xylo.partials.page-blocks.render', ['blocks' => $blocks, 'currency' => activeCurrency()])
                </div>
            @endif

            @forelse($collections as $collection)
                <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="jv-page-title mb-0">{{ $collection->name }}</h2>
                    </div>
                    @if($collection->rule_type)
                        <p class="text-muted small mb-2">{{ \App\Models\Collection::RULES[$collection->rule_type] ?? '' }}</p>
                    @endif
                    <div class="jv-grid">
                        @foreach($collection->previewProducts as $product)
                            @include('themes.xylo.partials.product-card', ['product' => $product, 'currency' => activeCurrency()])
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="jv-empty">This store has no collections yet.</p>
            @endforelse
        </div>
    </div>
@endsection
