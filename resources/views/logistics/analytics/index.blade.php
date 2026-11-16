@extends('logistics.layouts.app')

@section('title', 'Analytics & Reports')
@section('heading', 'Analytics & Reports')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Analytics</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Intelligence</span><h2>Logistics analytics</h2><p>Live operational, rider, zone and financial metrics from actual order records.</p></div>
    <form method="GET" class="d-flex gap-2">
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control">
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control">
        <button class="btn btn-juvo">Apply</button>
    </form>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Total orders', $kpis['totalOrders'], ''],
        ['Completed', $kpis['completed'], 'text-success'],
        ['Failed', $kpis['failed'], 'text-danger'],
        ['Cancelled', $kpis['cancelled'], 'text-muted'],
        ['In progress', $kpis['inProgress'], 'text-warning'],
        ['Success rate', $kpis['successRate'].'%', 'text-success'],
        ['Avg delivery', $kpis['avgMinutes'] !== null ? $kpis['avgMinutes'].' min' : '—', ''],
        ['Delivery revenue', number_format($kpis['deliveryRevenue'],2), ''],
        ['COD collected', number_format($kpis['codCollected'],2), ''],
        ['COD outstanding', number_format($kpis['codOutstanding'],2), 'text-warning'],
        ['COD reconciled', number_format($kpis['codReconciled'],2), 'text-success'],
        ['Active riders', $kpis['activeRiders'].' / '.$kpis['riders'], ''],
    ] as $card)
        <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">{{ $card[0] }}</div><div class="stat-value {{ $card[2] }}">{{ $card[1] }}</div></div></div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Daily order volume</h6></div>
            <div class="card-body"><canvas id="dailyChart" height="140"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Status breakdown</h6></div>
            <div class="card-body"><canvas id="statusChart" height="140"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Top locations</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Region</th><th>City</th><th>Orders</th></tr></thead>
                    <tbody>
                        @forelse($topLocations as $row)
                            <tr><td>{{ $row->state }}</td><td>{{ $row->city }}</td><td>{{ $row->orders }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Rider performance</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Rider</th><th>Completed</th><th>Failed</th></tr></thead>
                    <tbody>
                        @forelse($riderPerformance as $rider)
                            <tr><td>{{ $rider->full_name }}</td><td>{{ $rider->completed_count }}</td><td>{{ $rider->failed_count }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Zone performance</h6></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Zone</th><th>Locations</th><th>Orders</th><th>Completed</th><th>Success rate</th></tr></thead>
            <tbody>
                @forelse($zonePerformance as $row)
                    <tr><td>{{ $row['zone']->name }}</td><td>{{ $row['zone']->locations_count }}</td><td>{{ $row['orders'] }}</td><td>{{ $row['completed'] }}</td><td>{{ $row['rate'] }}%</td></tr>
                @empty
                    <tr><td colspan="5" class="empty-state">No zones configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const dailyCtx = document.getElementById('dailyChart');
if (dailyCtx) {
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: @json($daily->pluck('date')->map(fn($d)=>$d->format('M d'))),
            datasets: [
                {label:'Orders', data:@json($daily->pluck('orders')), borderColor:'#f68b1e', tension:.3, fill:false},
                {label:'Completed', data:@json($daily->pluck('completed')), borderColor:'#118a5b', tension:.3, fill:false},
                {label:'Failed', data:@json($daily->pluck('failed')), borderColor:'#d43f4b', tension:.3, fill:false}
            ]
        },
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}
    });
}
const statusCtx = document.getElementById('statusChart');
if (statusCtx) {
    new Chart(statusCtx, {
        type:'doughnut',
        data:{labels:@json($statusBreakdown->keys()), datasets:[{data:@json($statusBreakdown->values()), backgroundColor:['#f68b1e','#118a5b','#d43f4b','#687386','#2f6fed']}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}
    });
}
</script>
@endpush
