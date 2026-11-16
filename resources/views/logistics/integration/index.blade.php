@extends('logistics.layouts.app')

@section('title', 'Integration Monitoring')
@section('heading', 'Integration monitoring')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Integration</span>
@endsection

@section('content')
<div class="page-header"><div><span class="page-eyebrow">System</span><h2>Integration health</h2><p>Event synchronization status, failures, and authorized retries.</p></div></div>

<div class="row g-3 mb-4">
    @foreach([['Total', $kpis['total'], ''], ['Processed', $kpis['processed'], 'text-success'], ['Failed', $kpis['failed'], 'text-danger'], ['Pending', $kpis['pending'], 'text-warning']] as $card)
        <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">{{ $card[0] }}</div><div class="stat-value {{ $card[2] }}">{{ $card[1] }}</div></div></div>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Event ID</th><th>Type</th><th>Source</th><th>Target</th><th>Status</th><th>Attempts</th><th>Processed</th><th>Error</th><th></th></tr></thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td class="small">{{ $event->event_id }}</td>
                        <td>{{ $event->event_type }}</td>
                        <td>{{ $event->source }}</td>
                        <td>{{ $event->target }}</td>
                        <td>{{ $event->status }}</td>
                        <td>{{ $event->attempts }}</td>
                        <td>{{ optional($event->processed_at)->format('d M H:i') ?: '—' }}</td>
                        <td class="small text-danger">{{ $event->error }}</td>
                        <td class="text-end">@if($event->status === 'failed')<form method="POST" action="{{ route('logistics.integration.retry', $event) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger">Retry</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">No integration events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $events->links() }}</div>
</div>
@endsection
