@extends('agent.layouts.agent')

@section('title', 'My Sales & Performance')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">My Sales & Performance</h2>
            <p class="jt-muted mb-0">Performance for {{ $start->format('d M Y') }} to {{ $end->format('d M Y') }}.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('agent.performance.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-sm btn-outline-secondary">Export CSV</a>
        </div>
    </div>
</div>

<form method="GET" action="{{ route('agent.performance.index') }}" class="jt-card p-3 mb-3">
    <div class="row g-2">
        @foreach (['today'=>'Today','week'=>'This week','month'=>'This month','last_month'=>'Last month','quarter'=>'Quarter','year'=>'Year','custom'=>'Custom'] as $value => $label)
            <div class="col-auto">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="range" value="{{ $value }}" id="range_{{ $value }}" @checked(request('range','month') === $value)>
                    <label class="form-check-label" for="range_{{ $value }}">{{ $label }}</label>
                </div>
            </div>
        @endforeach
        <div class="col-md-2">
            <input type="date" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-dark">Apply</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    @foreach ([
        ['Gross sales', number_format($metrics['gross'],2)],
        ['Completed sales', number_format($metrics['completed_value'],2)],
        ['Orders', $metrics['order_count']],
        ['Delivered', $metrics['delivered_count']],
        ['Cancelled', $metrics['cancelled']],
        ['Returned', $metrics['returned']],
        ['Customers acquired', $metrics['customers_acquired']],
        ['Leads generated', $metrics['leads_generated']],
        ['Leads converted', $metrics['leads_converted']],
        ['Repeat customers', $metrics['repeat_customers']],
        ['Avg order value', number_format($metrics['avg_order_value'],2)],
        ['Growth', $metrics['growth'].'%'],
        ['Conversion rate', $metrics['conversion_rate'].'%'],
        ['Target', number_format($metrics['target'],2)],
        ['Target progress', $metrics['target_progress'].'%'],
        ['Commission earned', number_format($metrics['commission_earned'],2)],
        ['Commission pending', number_format($metrics['commission_pending'],2)],
        ['Commission eligible', number_format($metrics['commission_eligible'],2)],
    ] as $card)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="jt-card p-3 h-100">
                <div class="jt-muted" style="font-size:12px;">{{ $card[0] }}</div>
                <div class="fw-bold" style="font-size:20px;">{{ $card[1] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Sales Trend</h5>
            <canvas id="salesChart" height="180"></canvas>
            <table class="table table-sm mt-3 mb-0">
                <thead><tr><th>Date</th><th>Orders</th><th>Value</th></tr></thead>
                <tbody>
                    @foreach ($daily as $date => $data)
                        <tr><td>{{ $date }}</td><td>{{ $data['orders'] }}</td><td>{{ number_format($data['value'],2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Sales Funnel</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Stage</th><th>Count</th></tr></thead>
                <tbody>
                    <tr><td>Leads acquired</td><td>{{ $funnel['leads'] }}</td></tr>
                    <tr><td>Contacted</td><td>{{ $funnel['contacted'] }}</td></tr>
                    <tr><td>Interested</td><td>{{ $funnel['interested'] }}</td></tr>
                    <tr><td>Converted</td><td>{{ $funnel['converted'] }}</td></tr>
                    <tr><td>Customers ordering</td><td>{{ $funnel['ordering'] }}</td></tr>
                    <tr><td>Delivered customers</td><td>{{ $funnel['delivered'] }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Top Customers</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Customer</th><th>Orders</th><th>Value</th></tr></thead>
                <tbody>
                    @foreach ($topCustomers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->orders_count }}</td>
                            <td>{{ number_format((float) $customer->orders_sum_total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Product Performance</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Product</th><th>Qty</th><th>Value</th></tr></thead>
                <tbody>
                    @foreach ($productPerformance as $product)
                        <tr>
                            <td>{{ $product['name'] }}</td>
                            <td>{{ $product['qty'] }}</td>
                            <td>{{ number_format($product['value'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Category Performance</h5>
    <table class="table table-sm mb-0">
        <thead><tr><th>Category</th><th>Orders</th><th>Qty</th><th>Value</th></tr></thead>
        <tbody>
            @foreach ($categoryPerformance as $category => $data)
                <tr>
                    <td>{{ $category }}</td>
                    <td>{{ $data['orders'] }}</td>
                    <td>{{ $data['qty'] }}</td>
                    <td>{{ number_format($data['value'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Activity</h5>
    <div class="row g-2">
        @foreach ($activity as $key => $value)
            <div class="col-6 col-md-3">
                <div class="border rounded p-2">
                    <div class="jt-muted" style="font-size:12px;">{{ ucfirst(str_replace('_',' ',$key)) }}</div>
                    <div class="fw-bold">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Insights</h5>
    @forelse ($insights as $insight)
        <div class="mb-1">{{ $insight }}</div>
    @empty
        <div class="jt-muted">No insights available for this period.</div>
    @endforelse
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('salesChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json(array_keys($daily->toArray())),
            datasets: [{
                label: 'Sales value',
                data: @json(array_column($daily->toArray(), 'value')),
                borderColor: '#f68b1e',
                backgroundColor: 'rgba(246,139,30,.15)',
                fill: true,
                tension: .35
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
}
</script>
@endsection
