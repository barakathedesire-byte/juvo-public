@extends('admin.layouts.admin')
@section('title', 'System Health')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'System Health & Integration Monitoring',
        'subtitle' => 'Operational signals from the database, integration events, queues and notification delivery records.',
    ])

    <div class="ic-grid k4">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Overall status', 'value' => ucfirst($health['status']), 'hint' => 'Checked '.$health['checked_at']->format('H:i'), 'tone' => $health['status'] === 'healthy' ? 'good' : ($health['status'] === 'degraded' ? 'warn' : 'bad')])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Failed integrations', 'value' => number_format($health['integrations']['failed']), 'hint' => $health['integrations']['pending'].' pending or retrying', 'tone' => $health['integrations']['failed'] ? 'bad' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Failed jobs', 'value' => number_format($health['jobs']['failed']), 'hint' => $health['jobs']['pending'].' jobs pending', 'tone' => $health['jobs']['failed'] ? 'bad' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Failed notifications', 'value' => number_format($health['notifications']['failed']), 'hint' => $health['notifications']['pending'].' pending/queued', 'tone' => $health['notifications']['failed'] ? 'warn' : 'good'])
    </div>

    <div class="ic-grid k2 mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Health checks</h3><span class="meta">Actual signals, not page availability</span></div>
            <div class="ic-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Database</span><span class="ic-badge {{ $health['database']['status'] === 'healthy' ? 'good' : 'bad' }}">{{ $health['database']['status'] }}</span></div>
                <div class="ic-small ic-muted mb-3">{{ $health['database']['detail'] }}</div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Latest order record</span><span>{{ $health['latest_order_at'] ? \Illuminate\Support\Carbon::parse($health['latest_order_at'])->format('d M Y H:i') : 'No records' }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Latest delivery event</span><span>{{ $health['latest_delivery_event_at'] ? \Illuminate\Support\Carbon::parse($health['latest_delivery_event_at'])->format('d M Y H:i') : 'No records' }}</span></div>
                <div class="d-flex justify-content-between"><span class="ic-muted">Last integration success</span><span>{{ $health['integrations']['last_success'] ? \Illuminate\Support\Carbon::parse($health['integrations']['last_success'])->format('d M Y H:i') : 'No success recorded' }}</span></div>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Recent integration events</h3><a class="meta" href="{{ route('logistics.integration.index') }}">Logistics monitor</a></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Event</th><th>Status</th><th>Attempts</th><th>Created</th></tr></thead>
                    <tbody>
                    @forelse($integrations as $event)
                        <tr>
                            <td>{{ $event->event_type }}<div class="ic-small ic-muted">{{ $event->event_id }}</div></td>
                            <td><span class="ic-badge {{ in_array($event->status, ['failed', 'dead_letter']) ? 'bad' : ($event->status === 'processed' ? 'good' : 'warn') }}">{{ $event->status }}</span></td>
                            <td>{{ $event->attempts }}</td>
                            <td>{{ $event->created_at?->format('d M H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ic-muted">No integration events recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
