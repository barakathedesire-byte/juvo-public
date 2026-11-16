@extends('logistics.layouts.app')

@section('title', 'Rider Payout Statement')
@section('heading', 'Rider Payout Statement')
@section('breadcrumb')
    <span>Logistics</span>
    <span aria-hidden="true">/</span>
    <span>Payouts</span>
    <span aria-hidden="true">/</span>
    <span>{{ $rider->full_name }}</span>
@endsection

@section('content')
<div class="page-header">
    <div>
        <span class="page-eyebrow">Payouts &amp; Settlements</span>
        <h2>{{ $rider->full_name }}</h2>
        <p>Ledger balance, payout history, and settlement actions for this rider.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('logistics.payouts.index') }}" class="btn btn-outline-secondary btn-sm">Back to payouts</a>
        <a href="{{ route('logistics.finance.rider', $rider) }}" class="btn btn-outline-secondary btn-sm">COD statement</a>
        <button class="btn btn-juvo btn-sm" data-bs-toggle="modal" data-bs-target="#createPayout">Create payout</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Gross earnings</div><div class="stat-value">TZS {{ number_format($overview['gross_earnings'], 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Pending earnings</div><div class="stat-value">TZS {{ number_format($overview['pending_earnings'], 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Available</div><div class="stat-value text-success">TZS {{ number_format($overview['available'], 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Open payouts</div><div class="stat-value text-warning">TZS {{ number_format($overview['open'], 2) }}</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Ledger entries</h6></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Reference</th><th>Type</th><th>Direction</th><th>Amount</th><th>Status</th><th>Order</th><th>Payout</th><th>Occurred</th></tr></thead>
            <tbody>
                @forelse($ledger as $entry)
                    <tr>
                        <td>{{ $entry->reference }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($entry->type)) }}</td>
                        <td>{{ ucfirst($entry->direction) }}</td>
                        <td class="{{ $entry->direction === 'credit' ? 'text-success' : 'text-danger' }}">TZS {{ number_format((float) $entry->amount, 2) }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($entry->status)) }}</td>
                        <td>{{ $entry->order_id ? '#'.$entry->order_id : '—' }}</td>
                        <td>{{ $entry->payout?->reference ?? '—' }}</td>
                        <td>{{ optional($entry->occurred_at)->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state"><strong>No ledger entries</strong><p>Earnings and payouts will appear here.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $ledger->links() }}</div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Payouts</h6></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Reference</th><th>Amount</th><th>Method</th><th>Status</th><th>Provider reference</th><th>Initiated by</th><th>Approved by</th><th>Completed</th><th></th></tr></thead>
            <tbody>
                @forelse($payouts as $payout)
                    <tr>
                        <td><a href="{{ route('logistics.payouts.show', $payout) }}">{{ $payout->reference }}</a></td>
                        <td>TZS {{ number_format((float) $payout->amount, 2) }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($payout->method)) }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($payout->status)) }}</td>
                        <td>{{ $payout->provider_reference ?: '—' }}</td>
                        <td>{{ $payout->initiated_by_name }}</td>
                        <td>{{ $payout->approved_by_name ?: '—' }}</td>
                        <td>{{ optional($payout->completed_at)->format('d M Y H:i') ?: '—' }}</td>
                        <td class="text-end"><a href="{{ route('logistics.payouts.show', $payout) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state"><strong>No payouts</strong><p>Create a payout from available earnings.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $payouts->links('pagination::bootstrap-5') }}</div>
</div>

<div class="modal fade" id="createPayout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('logistics.payouts.store', $rider) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Create payout for {{ $rider->full_name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Available balance: TZS {{ number_format($overview['available'], 2) }}</p>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Amount (TZS)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select" required>
                            <option value="bank">Bank transfer</option>
                            <option value="mobile_money">Mobile money</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-select">
                            <option value="TZS">TZS</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Provider</label>
                        <input type="text" name="provider" class="form-control" maxlength="64">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Account name</label>
                        <input type="text" name="account_name" value="{{ $rider->bank_account_name }}" class="form-control" maxlength="120">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Account number</label>
                        <input type="text" name="account_number" value="{{ $rider->bank_account_number }}" class="form-control" maxlength="64">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Bank name</label>
                        <input type="text" name="bank_name" value="{{ $rider->bank_name }}" class="form-control" maxlength="80">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" maxlength="1000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-juvo">Create payout</button>
            </div>
        </form>
    </div>
</div>
@endsection
