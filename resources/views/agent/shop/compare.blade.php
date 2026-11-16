@extends('agent.layouts.agent')

@section('title', 'Compare Products')

@section('content')
<div class="jt-card p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="jt-section-title mb-0">Compare Products</h2>
        <a href="{{ route('agent.shop.index') }}" class="btn btn-sm btn-outline-secondary">Back to Shop</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Attribute</th>
                    @foreach ($products as $product)
                        <th>{{ $product->translation?->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Price</td>
                    @foreach ($products as $product)
                        @php $v = $product->variants->first(); @endphp
                        <td>{{ number_format((float) ($v?->discount_price ?? $v?->price), 2) }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Vendor</td>
                    @foreach ($products as $product)
                        <td>{{ $product->vendor?->shop_name ?: $product->vendor?->name }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Stock</td>
                    @foreach ($products as $product)
                        <td>{{ $product->variants->first()?->stock ?? 0 }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Category</td>
                    @foreach ($products as $product)
                        <td>{{ $product->category?->name }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td></td>
                    @foreach ($products as $product)
                        <td>
                            <form method="POST" action="{{ route('agent.shop.compare.remove', $product) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
