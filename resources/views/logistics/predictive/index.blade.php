@extends('logistics.layouts.app')

@section('title', 'Predictive Intelligence')
@section('heading', 'Predictive intelligence command center')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Predictive Intelligence</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Intelligence</span><h2>Predictive delivery intelligence</h2><p>Forecasts and risk assessments are based on available historical and live data; uncertainty is shown where data is limited.</p></div>
    <form method="GET" class="d-flex gap-2">
        <input type="number" name="days" min="1" max="30" value="{{ $days }}" class="form-control" style="width:90px;">
        <button class="btn btn-juvo">Apply</button>
        <a href="{{ route('logistics.predictive.index', ['days' => $days, 'generate_alerts' => 1]) }}" class="btn btn-outline-secondary">Generate alerts</a>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">High-risk deliveries</div><div class="stat-value text-danger">{{ $risks->whereIn('risk', ['high','critical'])->count() }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Expected orders ({{ $days }}d)</div><div class="stat-value">{{ $capacity['expected_orders'] }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Estimated capacity</div><div class="stat-value text-success">{{ $capacity['estimated_capacity'] }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Potential shortage</div><div class="stat-value text-warning">{{ $capacity['shortage'] }}</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Demand forecast</h6></div>
            <div class="card-body">
                <p class="small text-muted">Historical days: {{ $demand['historical_days'] }} · Recent daily average: {{ $demand['recent_daily_average'] }} · Confidence: {{ $demand['confidence'] }}% · {{ $demand['assumption'] }}</p>
                <canvas id="demandChart" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Failure concentration</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Location</th><th>Orders</th><th>Failed</th><th>Failure rate</th></tr></thead>
                    <tbody>
                        @forelse($failureRisks as $risk)
                            <tr><td>{{ $risk['city'] }}, {{ $risk['state'] }}</td><td>{{ $risk['orders'] }}</td><td>{{ $risk['failed'] }}</td><td>{{ $risk['failure_rate'] }}%</td></tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Not enough repeated location data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Delivery risk</h6></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order</th><th>Status</th><th>Risk</th><th>Score</th><th>Confidence</th><th>Factors</th></tr></thead>
            <tbody>
                @forelse($risks as $risk)
                    <tr>
                        <td>{{ $risk['order']->logistics_tracking_id ?? '#'.$risk['order']->id }}</td>
                        <td>{{ $risk['order']->logistics_status }}</td>
                        <td>{{ $risk['risk'] }}</td>
                        <td>{{ $risk['score'] }}</td>
                        <td>{{ $risk['confidence'] }}%</td>
                        <td class="small">{{ implode(' ', $risk['factors']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No active deliveries to assess.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recommendations</h6></div>
            <div class="card-body">
                @forelse($recommendations as $recommendation)
                    <div class="queue-item mb-2">
                        <div class="queue-body">
                            <strong>{{ $recommendation['title'] }}</strong>
                            <span>{{ $recommendation['reason'] }}</span>
                            <span class="text-muted small">Suggested action: {{ $recommendation['action'] }} · Confidence {{ $recommendation['confidence'] }}%</span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">No recommendations generated from current data.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Predictive alerts</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Severity</th><th>Title</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($alerts as $alert)
                            <tr>
                                <td>{{ $alert->severity }}</td>
                                <td>{{ $alert->title }}</td>
                                <td>{{ $alert->status }}</td>
                                <td class="text-end">
                                    @if($alert->status === 'open')
                                        <form method="POST" action="{{ route('logistics.predictive.alerts.acknowledge', $alert) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">Ack</button></form>
                                        <form method="POST" action="{{ route('logistics.predictive.alerts.resolve', $alert) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success">Resolve</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">No predictive alerts.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('demandChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json(collect($demand['forecast'])->pluck('date')->map(fn($d)=>$d->format('M d'))),
            datasets: [{label:'Expected orders', data:@json(collect($demand['forecast'])->pluck('expected')), borderColor:'#f68b1e', tension:.3, fill:false}]
        },
        options:{responsive:true,maintainAspectRatio:false}
    });
}
</script>
@endpush
