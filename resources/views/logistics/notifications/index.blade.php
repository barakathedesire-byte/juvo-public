@extends('logistics.layouts.app')

@section('title', 'Notifications & Automation')
@section('heading', 'Notifications & Automation')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Notifications</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Communication</span><h2>Notification center</h2><p>Centralized delivery tracking, channel health, templates, automation, and monitoring.</p></div>
    <div class="page-actions">
        <a href="{{ route('logistics.notifications.inbox') }}" class="btn btn-outline-secondary btn-sm">Inbox</a>
        <a href="{{ route('logistics.notifications.channels') }}" class="btn btn-outline-secondary btn-sm">Channels</a>
        <a href="{{ route('logistics.notifications.templates') }}" class="btn btn-outline-secondary btn-sm">Templates</a>
        <a href="{{ route('logistics.notifications.automation') }}" class="btn btn-outline-secondary btn-sm">Automation</a>
        <a href="{{ route('logistics.notifications.monitor') }}" class="btn btn-outline-secondary btn-sm">Monitor</a>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Total', $kpis['total'], ''],
        ['Sent', $kpis['sent'], 'text-success'],
        ['Delivered', $kpis['delivered'], 'text-success'],
        ['Failed', $kpis['failed'], 'text-danger'],
        ['Pending', $kpis['pending'], 'text-warning'],
        ['Scheduled rules', $kpis['scheduled'], ''],
        ['Retrying', $kpis['retrying'], 'text-warning'],
        ['Unread', $kpis['unread'], ''],
    ] as $card)
        <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">{{ $card[0] }}</div><div class="stat-value {{ $card[2] }}">{{ $card[1] }}</div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Volume by channel</h6></div>
            <div class="card-body">
                @forelse($volumeByChannel as $channel => $count)
                    <div class="d-flex justify-content-between mb-1"><span>{{ $channel }}</span><strong>{{ $count }}</strong></div>
                @empty
                    <div class="empty-state">No deliveries yet.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recent failures</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>ID</th><th>Channel</th><th>Recipient</th><th>Error</th><th>When</th></tr></thead>
                    <tbody>
                        @forelse($recentFailures as $failure)
                            <tr><td>{{ $failure->id }}</td><td>{{ $failure->channel }}</td><td>{{ $failure->recipient_type }} #{{ $failure->recipient_id }}</td><td class="small text-danger">{{ $failure->error_message }}</td><td>{{ optional($failure->failed_at)->format('d M Y H:i') }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No failures.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
