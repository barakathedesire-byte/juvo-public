@extends('agent.layouts.agent')

@section('title', 'Saved Products')

@section('content')
<div class="jt-card p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="jt-section-title mb-0">Saved Products</h2>
        <a href="{{ route('agent.shop.index') }}" class="btn btn-sm btn-outline-secondary">Back to Shop</a>
    </div>
    <div class="row g-3">
        @forelse ($products as $product)
            <div class="col-6 col-md-4 col-xl-3">
                <div class="jt-card p-2 h-100">
                    <img src="{{ $product->thumbnail ? \Illuminate\Support\Facades\Storage::url($product->thumbnail->image_url) : '' }}" alt="" style="width:100%;height:160px;object-fit:cover;border-radius:8px;">
                    <div class="fw-semibold mt-2">{{ $product->translation?->name }}</div>
                    <form method="POST" action="{{ route('agent.shop.unsave', $product) }}" class="mt-2">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
                </div>
            </div>
        @empty
            <div class="col-12 jt-muted text-center py-4">No saved products.</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $products->links() }}</div>
</div>
@endsection
