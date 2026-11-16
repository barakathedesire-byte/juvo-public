@extends('agent.layouts.agent')

@section('title', 'Product Details')

@section('content')
@php
    $variant = $product->variants->first();
    $price = $variant?->discount_price ?? $variant?->price;
    $original = $variant?->discount_price ? $variant->price : null;
@endphp

<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">{{ $product->translation?->name }}</h2>
            <p class="jt-muted mb-0">{{ $product->vendor?->shop_name ?: $product->vendor?->name }}</p>
        </div>
        <div class="d-flex gap-2">
            @if ($saved)
                <form method="POST" action="{{ route('agent.shop.unsave', $product) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary">Unsave</button></form>
            @else
                <form method="POST" action="{{ route('agent.shop.save', $product) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Save</button></form>
            @endif
            <form method="POST" action="{{ route('agent.shop.compare.add', $product) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Compare</button></form>
            <a href="{{ route('agent.shop.add-to-order', $product) }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Add to Order</a>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="jt-card p-3">
            <img src="{{ $product->thumbnail ? \Illuminate\Support\Facades\Storage::url($product->thumbnail->image_url) : '' }}" alt="" style="width:100%;height:320px;object-fit:cover;border-radius:8px;">
            @if ($product->images->count())
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    @foreach ($product->images as $image)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($image->image_url) }}" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:6px;">
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3">
            <div class="fw-bold" style="font-size:24px;">{{ number_format((float) $price, 2) }}</div>
            @if ($original)
                <div class="text-decoration-line-through jt-muted">{{ number_format((float) $original, 2) }}</div>
            @endif
            <div class="jt-muted mt-2">{{ $product->translation?->description }}</div>
            <div class="mt-2"><strong>Stock:</strong> {{ $variant?->stock ?? 0 }}</div>
            <div><strong>Rating:</strong> {{ number_format($product->averageRating(),1) }} ({{ $product->reviews->count() }} reviews)</div>
            <div><strong>Delivery:</strong> {{ $deliveryAvailable === null ? 'Select a location' : ($deliveryAvailable ? 'Available' : 'Not available') }}</div>

            <form method="GET" action="{{ route('agent.shop.show', $product) }}" class="mt-3">
                <label class="form-label">Check location</label>
                <select name="location_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Select location</option>
                    @foreach (\App\Models\Location::active()->ordered()->get() as $location)
                        <option value="{{ $location->id }}" @selected($location && $locationId == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </form>

            <div class="mt-3">
                <strong>Variants</strong>
                <ul class="mb-0">
                    @foreach ($product->variants as $v)
                        <li>{{ $v->SKU ?: 'Variant #'.$v->id }} — {{ number_format((float)($v->discount_price ?? $v->price),2) }} (stock: {{ $v->stock }})</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mt-3">
    <h5 class="mb-2">Active Promotions</h5>
    @forelse ($promotions as $promotion)
        <div class="border rounded p-2 mb-2">
            <strong>{{ $promotion->title }}</strong>
            <div class="jt-muted" style="font-size:12px;">{{ $promotion->starts_at?->format('d M Y') }} - {{ $promotion->ends_at?->format('d M Y') }}</div>
        </div>
    @empty
        <div class="jt-muted">No active promotions.</div>
    @endforelse
</div>

<div class="jt-card p-3 p-md-4 mt-3">
    <h5 class="mb-2">Related Products</h5>
    <div class="d-flex gap-2 flex-wrap">
        @foreach ($related as $relatedProduct)
            <a href="{{ route('agent.shop.show', $relatedProduct) }}" class="btn btn-sm btn-outline-secondary">{{ $relatedProduct->translation?->name }}</a>
        @endforeach
    </div>
</div>

<div class="jt-card p-3 p-md-4 mt-3">
    <h5 class="mb-2">Reviews</h5>
    @forelse ($product->reviews as $review)
        <div class="border-bottom py-2">
            <strong>{{ $review->customer?->name ?: 'Customer' }}</strong>
            <span class="jt-muted" style="font-size:12px;">{{ $review->rating }}/5</span>
            <div>{{ $review->review }}</div>
        </div>
    @empty
        <div class="jt-muted">No reviews yet.</div>
    @endforelse
</div>
@endsection
