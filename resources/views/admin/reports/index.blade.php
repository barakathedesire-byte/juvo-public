@extends('admin.layouts.admin')

@section('title', 'Sales reports')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Sales reports</h4>
        <a href="{{ route('admin.reports.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="btn btn-outline-secondary btn-sm">Export CSV</a>
    </div>

    <form method="GET" class="row g-2 align-items-end mt-3">
        <div class="col-auto"><label class="form-label small mb-0">From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control form-control-sm"></div>
        <div class="col-auto"><label class="form-label small mb-0">To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control form-control-sm"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
    </form>

    <div class="row g-3 mt-2">
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Orders</small><h4 class="mb-0">{{ number_format($summary['orders']) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Revenue</small><h4 class="mb-0">{{ number_format($summary['revenue'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Paid</small><h4 class="mb-0">{{ number_format($summary['paid'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Avg order</small><h4 class="mb-0">{{ number_format($summary['avg_order'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Commission</small><h4 class="mb-0">{{ number_format($summary['commission'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Discounts</small><h4 class="mb-0">{{ number_format($summary['discount'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Tax collected</small><h4 class="mb-0">{{ number_format($summary['tax'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Shipping</small><h4 class="mb-0">{{ number_format($summary['shipping'], 2) }}</h4></div></div></div>
    </div>

    <div class="row g-3 mt-3 mb-5">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">Daily revenue</div>
                <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Day</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr></thead>
                        <tbody>
                            @forelse($byDay as $row)
                                <tr><td>{{ $row->day }}</td><td class="text-end">{{ $row->orders }}</td><td class="text-end">{{ number_format($row->revenue, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No orders in range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">Top products</div>
                <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead>
                        <tbody>
                            @forelse($topProducts as $row)
                                <tr><td>{{ $row->product_name }}</td><td class="text-end">{{ $row->qty }}</td><td class="text-end">{{ number_format($row->revenue, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No sales in range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">Top vendors</div>
                <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Vendor</th><th class="text-end">Orders</th><th class="text-end">Revenue</th><th class="text-end">Commission</th></tr></thead>
                        <tbody>
                            @forelse($topVendors as $row)
                                <tr><td>{{ $row->vendor_name }}</td><td class="text-end">{{ $row->orders }}</td><td class="text-end">{{ number_format($row->revenue, 2) }}</td><td class="text-end">{{ number_format($row->commission, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No sales in range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
