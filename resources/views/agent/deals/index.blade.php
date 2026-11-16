@extends('agent.layouts.agent')

@section('title', 'Deals & Promotions')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <h2 class="jt-section-title mb-1">Deals & Promotions</h2>
    <p class="jt-muted mb-0">Discover approved Marketplace promotions and share them with customers.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-3">
    @foreach ([
        ['Active promotions', $metrics['active']],
        ['Upcoming', $metrics['upcoming']],
        ['Expiring soon', $metrics['expiring']],
        ['Products on offer', $metrics['products_on_offer']],
    ] as $card)
        <div class="col-6 col-md-3">
            <div class="jt-card p-3 h-100">
                <div class="jt-muted" style="font-size:12px;">{{ $card[0] }}</div>
                <div class="fw-bold" style="font-size:22px;">{{ $card[1] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <form method="GET" action="{{ route('agent.deals.index') }}" class="row g-2">
        <div class="col-md-3">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search campaigns">
        </div>
        <div class="col-md-2">
            <select name="filter" class="form-select">
                <option value="active" @selected(request('filter','active') === 'active')>Active</option>
                <option value="upcoming" @selected(request('filter') === 'upcoming')>Upcoming</option>
                <option value="expiring" @selected(request('filter') === 'expiring')>Expiring soon</option>
            </select>
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
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-sm btn-outline-dark">Filter</button>
            <a href="{{ route('agent.deals.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="row g-3">
    @forelse ($promotions as $promotion)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="jt-card p-2 h-100">
                <a href="{{ route('agent.deals.show', $promotion) }}">
                    <img src="{{ $promotion->product?->thumbnail ? \Illuminate\Support\Facades\Storage::url($promotion->product->thumbnail->image_url) : '' }}" alt="" style="width:100%;height:150px;object-fit:cover;border-radius:8px;">
                </a>
                <div class="fw-semibold mt-2">{{ $promotion->title }}</div>
                <div class="jt-muted" style="font-size:12px;">
                    {{ $promotion->starts_at?->format('d M Y') }} - {{ $promotion->ends_at?->format('d M Y') ?: 'No expiry' }}
                </div>
                <div class="mt-1">
                    <span class="badge bg-danger">{{ $promotion->type === 'percent' ? $promotion->value.'%' : number_format((float)$promotion->value,2) }} off</span>
                    <span class="badge bg-secondary">{{ $promotion->status }}</span>
                </div>
                <div class="d-flex gap-1 mt-2 flex-wrap">
                    <a href="{{ route('agent.deals.show', $promotion) }}" class="btn btn-sm btn-outline-dark">View</a>
                    @if ($promotion->product)
                        <a href="{{ route('agent.shop.show', $promotion->product) }}" class="btn btn-sm btn-outline-secondary">Product</a>
                        <a href="{{ route('agent.shop.add-to-order', $promotion->product) }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Add to Order</a>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="jt-muted text-center py-4">No promotions found.</div></div>
    @endforelse
</div>

<div class="mt-3">{{ $promotions->links() }}</div>

<div class="jt-card p-3 p-md-4 mt-3">
    <h5 class="mb-2">My Promotion Activity</h5>
    <table class="table table-sm mb-0">
        <thead><tr><th>Promotion</th><th>Product</th><th>Channel</th><th>Shared</th></tr></thead>
        <tbody>
            @forelse ($shares as $share)
                <tr>
                    <td>{{ $share->promotion?->title }}</td>
                    <td>{{ $share->product?->translation?->name }}</td>
                    <td>{{ $share->channel }}</td>
                    <td>{{ $share->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center jt-muted py-3">No promotion shares tracked yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="jt-muted small mt-2 mb-0">Customer reach and order attribution are shown only where supported by existing tracking data.</p>
</div>
@endsection
