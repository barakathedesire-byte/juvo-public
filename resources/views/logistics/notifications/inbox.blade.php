@extends('logistics.layouts.app')

@section('title', 'Notification Inbox')
@section('heading', 'Notification Inbox')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Notifications</span><span aria-hidden="true"> / </span><span>Inbox</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Communication</span><h2>Notification inbox</h2><p>Searchable delivery records with status, channel, recipient, and failure details.</p></div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Search dedup key</label><input name="q" value="{{ request('q') }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['queued','sent','delivered','failed','skipped','retrying'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Channel</label><select name="channel" class="form-select"><option value="">All</option>@foreach(['in_app','email','sms','push'] as $channel)<option value="{{ $channel }}" @selected(request('channel')===$channel)>{{ $channel }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Recipient type</label><input name="recipient_type" value="{{ request('recipient_type') }}" class="form-control"></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-juvo w-100">Filter</button><a class="btn btn-outline-secondary" href="{{ route('logistics.notifications.inbox') }}">Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Recipient</th><th>Category</th><th>Channel</th><th>Status</th><th>Queued</th><th>Sent</th><th>Delivered</th><th>Failed</th><th>Error</th></tr></thead>
            <tbody>
                @forelse($deliveries as $delivery)
                    <tr>
                        <td>{{ $delivery->id }}</td>
                        <td>{{ $delivery->recipient_type }} #{{ $delivery->recipient_id }}</td>
                        <td>{{ $delivery->category }}</td>
                        <td>{{ $delivery->channel }}</td>
                        <td>{{ $delivery->status }}</td>
                        <td>{{ optional($delivery->queued_at)->format('d M H:i') }}</td>
                        <td>{{ optional($delivery->sent_at)->format('d M H:i') ?: '—' }}</td>
                        <td>{{ optional($delivery->delivered_at)->format('d M H:i') ?: '—' }}</td>
                        <td>{{ optional($delivery->failed_at)->format('d M H:i') ?: '—' }}</td>
                        <td class="small text-danger">{{ $delivery->error_message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="empty-state">No notification deliveries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $deliveries->links() }}</div>
</div>
@endsection
