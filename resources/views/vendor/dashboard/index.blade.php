@extends('vendor.layouts.master')

@section('css')
<style>
    .dashboard-item {
        display: flex; align-items: center; padding: 15px; border-radius: 12px; border: 1px solid #e2e2e2; background: #fff; transition: all 0.3s ease;
    }
    .dashboard-item:hover { box-shadow: 0 4px 6px rgba(246, 139, 30, 0.15); }
    .icon-box { width: 55px; height: 55px; border-radius: 50%; background: #fff4e8; border: 1px solid #fbd9b3; display: flex; align-items: center; justify-content: center; margin-right: 15px; }
    .icon-box i { font-size: 22px; color: #f68b1e; }
    .text-box h6 { margin: 0; font-size: 16px; font-weight: 600; color: #2d2d2d; }
    .text-box p { margin: 0; font-size: 14px; color: #6c757d; }
    .jv-card { background:#fff; border:1px solid #ececec; border-radius:12px; padding:18px; height:100%; }
    .jv-card h6 { font-weight:600; margin-bottom:14px; color:#2d2d2d; }
    .jv-bar { height:10px; border-radius:6px; background:#f68b1e; }
    .jv-bar-track { background:#f1f1f1; border-radius:6px; height:10px; }
    .jv-mini { font-size:22px; font-weight:700; color:#f68b1e; }
</style>
@endsection

@section('content')
@php $sym = activeCurrency()->symbol; @endphp
<div class="container mt-4">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div>
            <h5 class="mb-0">Dashboard</h5>
            <small class="text-muted">Welcome back, {{ auth('vendor')->user()->name ?? 'Vendor' }}</small>
        </div>
        <div>
            <form method="GET" class="d-flex flex-wrap gap-2">
                <input type="date" class="form-control" name="from" value="{{ $from->format('Y-m-d') }}">
                <input type="date" class="form-control" name="to" value="{{ $to->format('Y-m-d') }}">
                <button class="btn btn-primary">Apply</button>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="dashboard-item">
                <div class="icon-box"><i class="fas fa-money-bill-trend-up"></i></div>
                <div class="text-box">
                    <h6>Gross Sales ({{ $from->format('d M') }} – {{ $to->format('d M') }})</h6>
                    <p>Gross: {{ $sym }}{{ number_format($data['grossSales'], 2) }}</p>
                    <p>Comm: {{ $sym }}{{ number_format($data['commission'], 2) }} | Net: {{ $sym }}{{ number_format($data['netEarnings'], 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="dashboard-item">
                <div class="icon-box"><i class="fas fa-shopping-bag"></i></div>
                <div class="text-box">
                    <h6>Orders</h6>
                    <p>Need action: {{ $data['requiringAction'] }} | Pending fulfil: {{ $data['pendingFulfillment'] }}</p>
                    <p>Completed: {{ $data['completedOrders'] }} | Cancelled: {{ $data['cancelledOrders'] }} | Total: {{ $data['totalOrders'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="dashboard-item">
                <div class="icon-box"><i class="fas fa-box-open"></i></div>
                <div class="text-box">
                    <h6>Products</h6>
                    <p>Published: {{ $data['publishedProducts'] }} / {{ $data['totalProducts'] }}</p>
                    <p>Out of stock: {{ $outOfStock }} | Low stock alerts: {{ $lowStock->count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="dashboard-item">
                <div class="icon-box"><i class="fas fa-coins"></i></div>
                <div class="text-box">
                    <h6>Earnings</h6>
                    <p>Available: {{ $sym }}{{ number_format($balances['available'], 2) }} | Reserved: {{ $sym }}{{ number_format($balances['reserved'], 2) }}</p>
                    <p><a href="{{ route('vendor.finance.index') }}" class="text-decoration-none">Go to Finance &amp; Payouts</a></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-7">
            <div class="jv-card h-100">
                <h6><i class="fas fa-trophy me-2" style="color:#f68b1e;"></i>Best performing products</h6>
                @if($bestProducts->isEmpty())
                    <p class="text-muted mb-0">No sales recorded yet.</p>
                @else
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                        <tbody>
                        @foreach($bestProducts as $row)
                            <tr><td>{{ $row->name }}</td><td class="text-end">{{ (int) $row->qty }}</td><td class="text-end">{{ $sym }}{{ number_format($row->revenue, 2) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="jv-card h-100">
                <h6><i class="fas fa-chart-line me-2" style="color:#f68b1e;"></i>Sales trend ({{ $from->format('M Y') }} – {{ $to->format('M Y') }})</h6>
                @if($salesTrend->isEmpty())
                    <p class="text-muted mb-0">Not enough data yet.</p>
                @else
                    @foreach($salesTrend as $point)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">{{ $point->ym }}</small>
                                <small class="fw-semibold">{{ $sym }}{{ number_format($point->total, 0) }}</small>
                            </div>
                            <div class="jv-bar-track"><div class="jv-bar" style="width: {{ round(($point->total / $maxTrend) * 100) }}%;"></div></div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <div class="jv-card h-100">
                <h6><i class="fas fa-bell me-2" style="color:#f68b1e;"></i>Low stock alerts</h6>
                @if($lowStock->isEmpty())
                    <p class="text-muted mb-0">All good.</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach($lowStock as $v)
                            <li class="d-flex justify-content-between border-bottom py-1">
                                <span>{{ $v->product?->translation?->name ?? 'Product' }} ({{ $v->sku }})</span>
                                <span class="fw-semibold">{{ $v->stock }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="jv-card h-100">
                <h6><i class="fas fa-list-check me-2" style="color:#f68b1e;"></i>Onboarding checklist</h6>
                <ul class="list-unstyled mb-0">
                    @foreach($onboarding as $item)
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="fas {{ $item['done'] ? 'fa-check-circle text-success' : 'fa-circle text-muted' }} me-1"></i>{{ $item['label'] }}</span>
                            <small class="text-muted">{{ $item['remaining'] }}</small>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="jv-card h-100">
                <h6><i class="fas fa-bolt me-2" style="color:#f68b1e;"></i>Quick actions</h6>
                <div class="d-flex flex-column gap-2">
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('vendor.products.create') }}"><i class="fas fa-plus me-1"></i>Add product</a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('vendor.orders.index') }}"><i class="fas fa-shopping-cart me-1"></i>Process orders ({{ $data['requiringAction'] }})</a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('vendor.finance.payouts') }}"><i class="fas fa-money-bill-transfer me-1"></i>Request payout</a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('vendor.settings.index') }}"><i class="fas fa-store me-1"></i>Update store</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="jv-card h-100">
                <h6>Recent orders</h6>
                @if($recentOrders->isEmpty())
                    <p class="text-muted mb-0">No orders yet.</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach($recentOrders as $order)
                            <li class="d-flex justify-content-between border-bottom py-1">
                                <span>#{{ $order->id }} — {{ $order->statusLabel() }}</span>
                                <span class="fw-semibold">{{ $sym }}{{ number_format((float)$order->total_amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <div class="col-lg-6">
            <div class="jv-card h-100">
                <h6>Recent transactions</h6>
                @if($recentTransactions->isEmpty())
                    <p class="text-muted mb-0">No transactions yet.</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach($recentTransactions as $t)
                            <li class="d-flex justify-content-between border-bottom py-1">
                                <span>{{ $t->created_at->format('d M H:i') }} — {{ ucfirst($t->type) }}</span>
                                <span class="{{ $t->amount<0?'text-danger':'text-success' }}">{{ $t->amount<0?'-':'+' }}{{ number_format(abs((float)$t->amount), 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
