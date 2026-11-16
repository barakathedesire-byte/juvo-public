@extends('logistics.layouts.app')

@section('title', 'Payout Details')
@section('heading', 'Payout Details')
@section('breadcrumb')
    <span>Logistics</span>
    <span aria-hidden="true">/</span>
    <span>Payouts</span>
    <span aria-hidden="true">/</span>
    <span>{{ $payout->reference }}</span>
@endsection

@section('content')
<div class="page-header">
    <div>
        <span class="page-eyebrow">Payouts &amp; Settlements</span>
        <h2>{{ $payout->reference }}</h2>
        <p>{{ $payout->rider?->full_name }} · TZS {{ number_format((float) $payout->amount, 2) }} · {{ str_replace('_', ' ', ucfirst($payout->status)) }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('logistics.payouts.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        @if($payout->rider)
            <a href="{{ route('logistics.payouts.rider', $payout->rider) }}" class="btn btn-outline-secondary btn-sm">Rider statement</a>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Amount</div><div class="stat-value">TZS {{ number_format((float) $payout->amount, 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Status</div><div class="stat-value">{{ str_replace('_', ' ', ucfirst($payout->status)) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Method</div><div class="stat-value">{{ str_replace('_', ' ', ucfirst($payout->method)) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Available now</div><div class="stat-value text-success">TZS {{ number_format($overview['available'], 2) }}</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Payout details</h6></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Rider</dt><dd class="col-sm-8">{{ $payout->rider?->full_name }}</dd>
                    <dt class="col-sm-4">Currency</dt><dd class="col-sm-8">{{ $payout->currency }}</dd>
                    <dt class="col-sm-4">Account name</dt><dd class="col-sm-8">{{ $payout->account_name ?: '—' }}</dd>
                    <dt class="col-sm-4">Account number</dt><dd class="col-sm-8">{{ $payout->account_number ?: '—' }}</dd>
                    <dt class="col-sm-4">Bank</dt><dd class="col-sm-8">{{ $payout->bank_name ?: '—' }}</dd>
                    <dt class="col-sm-4">Provider</dt><dd class="col-sm-8">{{ $payout->provider ?: '—' }}</dd>
                    <dt class="col-sm-4">Provider reference</dt><dd class="col-sm-8">{{ $payout->provider_reference ?: '—' }}</dd>
                    <dt class="col-sm-4">Initiated by</dt><dd class="col-sm-8">{{ $payout->initiated_by_name }} · {{ optional($payout->created_at)->format('d M Y H:i') }}</dd>
                    <dt class="col-sm-4">Approved by</dt><dd class="col-sm-8">{{ $payout->approved_by_name ?: '—' }} @if($payout->approved_at)· {{ $payout->approved_at->format('d M Y H:i') }}@endif</dd>
                    <dt class="col-sm-4">Completed by</dt><dd class="col-sm-8">{{ $payout->completed_by_name ?: '—' }} @if($payout->completed_at)· {{ $payout->completed_at->format('d M Y H:i') }}@endif</dd>
                    <dt class="col-sm-4">Failure reason</dt><dd class="col-sm-8">{{ $payout->failure_reason ?: '—' }}</dd>
                    <dt class="col-sm-4">Notes</dt><dd class="col-sm-8">{{ $payout->notes ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Actions</h6></div>
            <div class="card-body d-grid gap-2">
                @if($payout->status === 'pending_approval')
                    <form method="POST" action="{{ route('logistics.payouts.approve', $payout) }}">
                        @csrf
                        <button class="btn btn-success w-100" type="submit">Approve payout</button>
                    </form>
                    <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectPayout">Reject payout</button>
                @endif

                @if(in_array($payout->status, ['approved', 'processing'], true))
                    <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#completePayout">Complete payout</button>
                    <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#failPayout">Mark failed</button>
                @endif

                @if($payout->status === 'completed')
                    <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#reversePayout">Reverse payout</button>
                @endif

                @if(!in_array($payout->status, ['pending_approval', 'approved', 'processing'], true))
                    <div class="text-muted small">No further actions are available for this payout.</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Audit trail</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Action</th><th>Actor</th><th>When</th></tr></thead>
                        <tbody>
                            @forelse($audit as $log)
                                <tr>
                                    <td>{{ $log->action }}</td>
                                    <td>{{ $log->actor_name ?: 'System' }}</td>
                                    <td>{{ optional($log->created_at)->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="empty-state py-3">No audit entries.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectPayout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('logistics.payouts.reject', $payout) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Reject payout</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="4" required maxlength="1000"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="completePayout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('logistics.payouts.complete', $payout) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Complete payout</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Provider reference</label>
                    <input type="text" name="provider_reference" class="form-control" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label">Provider</label>
                    <input type="text" name="provider" value="{{ $payout->provider }}" class="form-control" maxlength="64">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="confirmed" value="1" id="confirmPayout" required>
                    <label class="form-check-label" for="confirmPayout">I verified the provider or bank transfer.</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Complete</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="failPayout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('logistics.payouts.fail', $payout) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Mark payout failed</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="4" required maxlength="1000"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Mark failed</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="reversePayout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('logistics.payouts.reverse', $payout) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Reverse completed payout</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="4" required maxlength="1000"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reverse</button>
            </div>
        </form>
    </div>
</div>
@endsection
