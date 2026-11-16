@extends('agent.layouts.agent')

@section('title', 'Shop / Product Catalogue')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">Shop / Product Catalogue</h2>
            <p class="jt-muted mb-0">Browse live Marketplace products and add them to customer orders.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('agent.shop.saved') }}" class="btn btn-sm btn-outline-dark">Saved</a>
            <a href="{{ route('agent.shop.compare') }}" class="btn btn-sm btn-outline-dark">Compare</a>
            <a href="{{ route('agent.place-order.index') }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Place Order</a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="jt-card p-3 p-md-4 mb-3">
    <form method="GET" action="{{ route('agent.shop.index') }}" class="row g-2">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search products">
        </div>
        <div class="col-md-2">
            <select name="category_id" class="form-select">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->displayName() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="vendor_id" class="form-select">
                <option value="">All vendors</option>
                @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected(request('vendor_id') == $vendor->id)>{{ $vendor->shop_name ?: $vendor->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="sort" class="form-select">
                <option value="newest" @selected(request('sort','newest') === 'newest')>Newest</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>Price low-high</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>Price high-low</option>
                <option value="rating" @selected(request('sort') === 'rating')>Rating</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <label class="form-check mt-2"><input type="checkbox" name="available" value="1" class="form-check-input" @checked(request('available') === '1')> Available</label>
            <label class="form-check mt-2"><input type="checkbox" name="discounted" value="1" class="form-check-input" @checked(request('discounted') === '1')> Discounted</label>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-sm btn-outline-dark">Filter</button>
            <a href="{{ route('agent.shop.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3">
            <h5 class="mb-2">Featured</h5>
            <div class="row g-2">
                @foreach ($featured as $product)
                    <div class="col-6">
                        <a href="{{ route('agent.shop.show', $product) }}" class="text-decoration-none">
                            <div class="border rounded p-2">
                                <img src="{{ $product->thumbnail ? \Illuminate\Support\Facades\Storage::url($product->thumbnail->image_url) : '' }}" alt="" style="width:100%;height:120px;object-fit:cover;">
                                <div class="fw-semibold mt-1" style="font-size:13px;">{{ $product->translation?->name }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3">
            <h5 class="mb-2">Popular Categories</h5>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($popularCategories as $category)
                    <a href="{{ route('agent.shop.index', ['category_id' => $category->id]) }}" class="btn btn-sm btn-outline-secondary">{{ $category->displayName() }} ({{ $category->products_count }})</a>
                @endforeach
            </div>
            <h5 class="mb-2 mt-3">Active Promotions</h5>
            <ul class="mb-0">
                @foreach ($promotions as $promotion)
                    <li>{{ $promotion->title }} @if($promotion->ends_at) until {{ $promotion->ends_at->format('d M') }} @endif</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<div class="row g-3">
    @forelse ($products as $product)
        @php
            $variant = $product->variants->first();
            $price = $variant?->discount_price ?? $variant?->price;
            $original = $variant?->discount_price ? $variant->price : null;
            $discount = ($original && $price) ? round((1 - $price / $original) * 100) : null;
        @endphp
        <div class="col-6 col-md-4 col-xl-3">
            <div class="jt-card p-2 h-100">
                <a href="{{ route('agent.shop.show', $product) }}">
                    <img src="{{ $product->thumbnail ? \Illuminate\Support\Facades\Storage::url($product->thumbnail->image_url) : '' }}" alt="" style="width:100%;height:160px;object-fit:cover;border-radius:8px;">
                </a>
                <div class="fw-semibold mt-2" style="font-size:14px;">{{ $product->translation?->name }}</div>
                <div class="jt-muted" style="font-size:12px;">{{ $product->vendor?->shop_name ?: $product->vendor?->name }}</div>
                <div class="mt-1">
                    <span class="fw-bold">{{ number_format((float) $price, 2) }}</span>
                    @if ($original)
                        <span class="text-decoration-line-through jt-muted" style="font-size:12px;">{{ number_format((float) $original, 2) }}</span>
                    @endif
                    @if ($discount)
                        <span class="badge bg-danger">-{{ $discount }}%</span>
                    @endif
                </div>
                <div class="jt-muted" style="font-size:12px;">Stock: {{ $variant?->stock ?? 0 }} · Rating: {{ number_format($product->averageRating(),1) }}</div>
                <div class="d-flex gap-1 mt-2 flex-wrap">
                    <a href="{{ route('agent.shop.show', $product) }}" class="btn btn-sm btn-outline-dark">View</a>
                    @if (in_array($product->id, $savedIds))
                        <form method="POST" action="{{ route('agent.shop.unsave', $product) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary">Unsave</button></form>
                    @else
                        <form method="POST" action="{{ route('agent.shop.save', $product) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Save</button></form>
                    @endif
                    <form method="POST" action="{{ route('agent.shop.compare.add', $product) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Compare</button></form>
                    <a href="{{ route('agent.shop.add-to-order', $product) }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Add to Order</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="jt-muted text-center py-4">No products found.</div></div>
    @endforelse
</div>

<div class="mt-3">{{ $products->links() }}</div>

@if ($recentlyViewed->count())
    <div class="jt-card p-3 mt-3">
        <h5 class="mb-2">Recently Viewed</h5>
        <div class="d-flex gap-2 flex-wrap">
            @foreach ($recentlyViewed as $product)
                <a href="{{ route('agent.shop.show', $product) }}" class="btn btn-sm btn-outline-secondary">{{ $product->translation?->name }}</a>
            @endforeach
        </div>
    </div>
@endif
@endsection
