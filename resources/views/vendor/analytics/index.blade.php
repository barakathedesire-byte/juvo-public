@extends('vendor.layouts.master')

@section('title', 'Analytics')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="mb-0"><i class="fas fa-chart-line me-2" style="color:#f68b1e;"></i>Analytics</h4>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
            <input type="date" name="from" class="form-control form-control-sm" value="{{ $from->format('Y-m-d') }}">
            <span class="text-muted">to</span>
            <input type="date" name="to" class="form-control form-control-sm" value="{{ $to->format('Y-m-d') }}">
            <button class="btn btn-sm btn-primary">Apply</button>
            <a href="{{ route('vendor.analytics.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-success">
                <i class="fas fa-download me-1"></i>Export CSV
            </a>
        </form>
    </div>

    <div class="row g-3 mb-3">
        @php
            $cards = [
                ['Gross sales', number_format($kpis['gross_sales'], 2), 'fa-sack-dollar', 'text-success'],
                ['Net sales', number_format($kpis['net_sales'], 2), 'fa-wallet', 'text-primary'],
                ['Completed orders', $kpis['completed_orders'], 'fa-box-check', 'text-info'],
                ['Units sold', $kpis['units_sold'], 'fa-cubes', 'text-secondary'],
                ['Avg order value', number_format($kpis['average_order_value'], 2), 'fa-receipt', 'text-dark'],
                ['Refunds', number_format($kpis['refunds'], 2), 'fa-rotate-left', 'text-danger'],
                ['Completion rate', $kpis['conversion_rate'].'%', 'fa-percent', 'text-warning'],
                ['Cancelled orders', $kpis['cancelled_orders'], 'fa-ban', 'text-danger'],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $icon, $color])
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small">{{ $label }}</div>
                                <div class="fs-4 fw-bold">{{ $value }}</div>
                            </div>
                            <i class="fas {{ $icon }} {{ $color }} fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header card-header-bg"><h6 class="mb-0 text-white">Daily revenue (completed orders)</h6></div>
                <div class="card-body">
                    @if ($daily->isEmpty())
                        <p class="text-muted mb-0">No completed sales in this period.</p>
                    @else
                        <div class="d-flex align-items-end gap-1" style="height:220px;overflow-x:auto;">
                            @foreach ($daily as $row)
                                @php $h = max(4, (int) round(((float) $row->revenue / $maxDaily) * 200)); @endphp
                                <div class="text-center" style="min-width:26px;" title="{{ $row->day }}: {{ number_format((float) $row->revenue, 2) }} ({{ $row->orders }} orders)">
                                    <div style="height:{{ $h }}px;width:18px;background:#f68b1e;border-radius:3px 3px 0 0;margin:0 auto;"></div>
                                    <div class="small text-muted" style="writing-mode:vertical-rl;transform:rotate(180deg);font-size:9px;">
                                        {{ \Carbon\Carbon::parse($row->day)->format('d M') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header card-header-bg"><h6 class="mb-0 text-white">Orders by status</h6></div>
                <div class="card-body">
                    @forelse ($statusBreakdown as $row)
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>{{ ucwords(str_replace('_', ' ', $row->status)) }}</span>
                            <span class="fw-semibold">{{ $row->total }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No orders in this period.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg"><h6 class="mb-0 text-white">Top products by revenue</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th class="text-end">Units</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topProducts as $i => $row)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td class="text-end">{{ $row->units }}</td>
                                        <td class="text-end">{{ number_format((float) $row->revenue, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">No product sales in this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
