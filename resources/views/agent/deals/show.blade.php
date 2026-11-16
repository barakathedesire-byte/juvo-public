@extends('agent.layouts.agent')

@section('title', 'Promotion Details')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">{{ $promotion->title }}</h2>
            <p class="jt-muted mb-0">{{ $promotion->type }} — {{ $promotion->value }} off</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('agent.deals.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
            @if ($promotion->product)
                <a href="{{ route('agent.shop.show', $promotion->product) }}" class="btn btn-sm btn-outline-dark">Open Product</a>
                <a href="{{ route('agent.shop.add-to-order', $promotion->product) }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Start Order</a>
            @endif
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Campaign Details</h5>
            <div><strong>Status:</strong> {{ $promotion->status }}</div>
            <div><strong>Funding:</strong> {{ $promotion->funding_source }}</div>
            <div><strong>Vendor:</strong> {{ $promotion->vendor?->shop_name ?: $promotion->vendor?->name ?: '—' }}</div>
            <div><strong>Category:</strong> {{ optional(\App\Models\Category::find($promotion->category_id))->displayName() ?: '—' }}</div>
            <div><strong>Start:</strong> {{ $promotion->starts_at?->format('d M Y') ?: '—' }}</div>
            <div><strong>Expiry:</strong> {{ $promotion->ends_at?->format('d M Y') ?: '—' }}</div>
            <div><strong>Budget:</strong> {{ $promotion->budget_amount ? number_format((float)$promotion->budget_amount,2) : 'Uncapped' }}</div>
            <div><strong>Remaining budget:</strong> {{ $promotion->remainingBudget() === null ? 'Uncapped' : number_format($promotion->remainingBudget(),2) }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Share Promotion</h5>
            <form method="POST" action="{{ route('agent.deals.share', $promotion) }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $promotion->product_id }}">
                <input type="hidden" name="channel" value="link">
                <button class="btn btn-sm btn-outline-secondary mb-2">Track link share</button>
            </form>
            @if ($promotion->product)
                <a href="https://wa.me/?text={{ urlencode($promotion->title.' '.route('agent.shop.show', $promotion->product)) }}" target="_blank" class="btn btn-sm btn-success">Share on WhatsApp</a>
            @endif
            <p class="jt-muted mt-2 mb-0">Sharing records activity only; engagement metrics are shown only where supported by backend tracking.</p>
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Eligible Products</h5>
    <div class="row g-3">
        @forelse ($eligibleProducts as $product)
            <div class="col-6 col-md-4 col-xl-3">
                <div class="jt-card p-2 h-100">
                    <img src="{{ $product->thumbnail ? \Illuminate\Support\Facades\Storage::url($product->thumbnail->image_url) : '' }}" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:8px;">
                    <div class="fw-semibold mt-2">{{ $product->translation?->name }}</div>
                    <div class="jt-muted" style="font-size:12px;">Stock: {{ $product->variants->first()?->stock ?? 0 }}</div>
                    <div class="d-flex gap-1 mt-2">
                        <a href="{{ route('agent.shop.show', $product) }}" class="btn btn-sm btn-outline-dark">View</a>
                        <a href="{{ route('agent.shop.add-to-order', $product) }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Add to Order</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 jt-muted text-center py-4">No eligible products.</div>
        @endforelse
    </div>
</div>
@endsection
