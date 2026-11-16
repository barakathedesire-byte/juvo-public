@extends('logistics.layouts.app')

@section('title', 'Automation Monitor')
@section('heading', 'Automation Monitor')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Notifications</span><span aria-hidden="true"> / </span><span>Monitor</span>
@endsection

@section('content')
<div class="page-header"><div><span class="page-eyebrow">Automation</span><h2>Monitor</h2><p>Execution history, failures, and retry controls for logistics automation rules.</p></div></div>

<div class="row g-3 mb-4">
    @foreach([['Total', $kpis['total'], ''], ['Succeeded', $kpis['succeeded'], 'text-success'], ['Failed', $kpis['failed'], 'text-danger'], ['Retrying', $kpis['retrying'], 'text-warning']] as $card)
        <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">{{ $card[0] }}</div><div class="stat-value {{ $card[2] }}">{{ $card[1] }}</div></div></div>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Rule</th><th>Trigger</th><th>Status</th><th>Attempt</th><th>Started</th><th>Finished</th><th>Error</th><th></th></tr></thead>
            <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td>{{ $run->workflow?->name }}</td>
                        <td>{{ $run->trigger }}</td>
                        <td>{{ $run->status }}</td>
                        <td>{{ $run->attempt }}/{{ $run->max_attempts }}</td>
                        <td>{{ optional($run->started_at)->format('d M H:i') }}</td>
                        <td>{{ optional($run->finished_at)->format('d M H:i') }}</td>
                        <td class="small text-danger">{{ $run->error }}</td>
                        <td class="text-end">@if(in_array($run->status, ['failed','dead_letter']))<form method="POST" action="{{ route('logistics.notifications.monitor.retry', $run) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger">Retry</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">No automation runs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $runs->links() }}</div>
</div>
@endsection
