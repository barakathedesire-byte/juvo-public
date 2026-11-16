@extends('admin.layouts.admin')
@section('title', 'Marketplace Intelligence')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'Marketplace Intelligence',
        'subtitle' => 'Order volume, demand distribution, category and product performance, and vendor fulfillment patterns.',
    ])

    <div class="ic-grid k4">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Total orders', 'value' => number_format($data['orders']), 'hint' => number_format($data['growth'], 1).'% vs previous period', 'tone' => $data['growth'] < 0 ? 'warn' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Confirmed', 'value' => number_format($data['confirmed']), 'hint' => number_format($data['awaiting_vendor']).' awaiting vendor action'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Completed', 'value' => number_format($data['completed']), 'hint' => number_format($data['canceled']).' canceled'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Average order value', 'value' => number_format($data['average_order_value'], 2), 'hint' => 'Calculated from non-canceled orders'])
    </div>

    <div class="ic-split mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Order volume</h3><span class="meta">Daily, selected period</span></div>
            <div class="ic-card-body">@include('admin.command-center.intelligence._trend', ['trend' => $data['trend']])</div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Status distribution</h3><span class="meta">Current order records</span></div>
            <div class="ic-card-body">
                @forelse($data['status_distribution'] as $row)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between ic-small"><span>{{ $row['label'] }}</span><strong>{{ number_format($row['count']) }} &middot; {{ $row['percentage'] }}%</strong></div>
                        <div class="ic-bar mt-1"><span style="width:{{ min(100, $row['percentage']) }}%"></span></div>
                    </div>
                @empty
                    <div class="ic-empty">No orders in this period.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="ic-grid k2 mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Category performance</h3><span class="meta">Units in active order states</span></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Category</th><th>Units</th><th>Orders</th></tr></thead>
                    <tbody>
                    @forelse($data['category_performance'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['quantity']) }}</td><td>{{ number_format($row['orders']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="ic-muted">No order-detail records available.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Product demand</h3><span class="meta">Top products by units</span></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Product</th><th>Units</th><th>Orders</th><th>Stock</th></tr></thead>
                    <tbody>
                    @forelse($data['product_demand']->take(15) as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['quantity']) }}</td><td>{{ number_format($row['orders']) }}</td><td>{{ number_format($row['stock']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="ic-muted">No product demand records available.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ic-grid k2 mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Regional demand</h3><a class="meta" href="{{ route('admin.command-center.geography') }}">Open geography</a></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Region</th><th>Orders</th><th>Delivered</th><th>Failed</th></tr></thead>
                    <tbody>
                    @forelse($data['region_demand'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['orders']) }}</td><td>{{ number_format($row['delivered']) }}</td><td>{{ number_format($row['failed']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="ic-muted">No regional records available.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Vendor fulfillment</h3><a class="meta" href="{{ route('admin.command-center.vendors') }}">Open vendors</a></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Vendor</th><th>Orders</th><th>Completion</th><th>Cancellations</th></tr></thead>
                    <tbody>
                    @forelse($data['vendor_performance'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['total_orders']) }}</td><td>{{ $row['completion_rate'] }}%</td><td>{{ $row['cancellation_rate'] }}%</td></tr>
                    @empty
                        <tr><td colspan="4" class="ic-muted">No vendor orders in this period.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
