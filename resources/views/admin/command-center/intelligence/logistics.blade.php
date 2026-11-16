@extends('admin.layouts.admin')
@section('title', 'Logistics Intelligence')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'Logistics Intelligence',
        'subtitle' => 'Delivery volume, completion, delays, rider capacity and geographic delivery performance.',
    ])

    <div class="ic-grid k4">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Deliveries', 'value' => number_format($data['total']), 'hint' => number_format($data['pending_dispatch']).' pending dispatch'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Delivered', 'value' => number_format($data['completed']), 'hint' => $data['success_rate'].'% success rate', 'tone' => $data['success_rate'] >= 80 ? 'good' : 'warn'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Delayed', 'value' => number_format($data['delayed']), 'hint' => 'Past scheduled date or older than 24h', 'tone' => $data['delayed'] > 0 ? 'warn' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Average duration', 'value' => $data['average_duration_minutes'] === null ? 'N/A' : $data['average_duration_minutes'].' min', 'hint' => 'Delivered records with timestamps'])
    </div>

    <div class="ic-grid k4 mt-3">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Assigned', 'value' => number_format($data['assigned']), 'hint' => number_format($data['in_transit']).' in transit'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Failed / returned', 'value' => number_format($data['failed']), 'hint' => 'From delivery status records', 'tone' => $data['failed'] > 0 ? 'warn' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Riders online', 'value' => number_format($data['online_riders']), 'hint' => number_format($data['active_riders']).' active riders'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Rider utilisation', 'value' => $data['rider_utilization'].'%', 'hint' => number_format($data['busy_riders']).' currently busy'])
    </div>

    <div class="ic-grid k2 mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Delivery status</h3><span class="meta">All logistics records in period</span></div>
            <div class="ic-card-body">
                @forelse($data['status_distribution'] as $row)
                    @if($row['count'] > 0)
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="ic-small">{{ $row['label'] }}</span>
                        <span><strong>{{ number_format($row['count']) }}</strong> <span class="ic-badge ml-2">{{ $row['status'] }}</span></span>
                    </div>
                    @endif
                @empty
                    <div class="ic-empty">No logistics records available.</div>
                @endforelse
                <div class="d-flex justify-content-between mt-3"><span class="ic-muted">Dispatch acceptance rate</span><strong>{{ $data['dispatch_acceptance_rate'] }}%</strong></div>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Location performance</h3><a class="meta" href="{{ route('admin.command-center.geography') }}">Geography</a></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Location</th><th>Deliveries</th><th>Failed</th><th>Failure rate</th></tr></thead>
                    <tbody>
                    @forelse($data['location_performance']->take(15) as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['total']) }}</td><td>{{ number_format($row['failed']) }}</td><td>{{ $row['failure_rate'] }}%</td></tr>
                    @empty
                        <tr><td colspan="4" class="ic-muted">No location delivery records.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Rider performance</h3><span class="meta">Assigned delivery records in period</span></div>
        <div class="ic-card-body ic-scroll">
            <table class="ic-table">
                <thead><tr><th>Rider</th><th>Deliveries</th><th>Delivered</th><th>Failed / returned</th><th>Success rate</th></tr></thead>
                <tbody>
                @forelse($data['rider_performance'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['deliveries']) }}</td><td>{{ number_format($row['delivered']) }}</td><td>{{ number_format($row['failed']) }}</td><td>{{ $row['success_rate'] }}%</td></tr>
                @empty
                    <tr><td colspan="5" class="ic-muted">No rider assignment records in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
