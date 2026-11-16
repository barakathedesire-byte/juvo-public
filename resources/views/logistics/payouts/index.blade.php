@extends('logistics.layouts.app')

@section('title', 'Payouts & Settlements')
@section('heading', 'Payouts & Settlements')
@section('breadcrumb')
    <span>Logistics</span>
    <span aria-hidden="true">/</span>
    <span>Payouts</span>
@endsection

@section('content')
<div class="page-header">
    <div>
        <span class="page-eyebrow">Finance</span>
        <h2>Rider payouts and settlements</h2>
        <p>Create, approve, complete, and reverse rider payouts with ledger-backed balances and audit trails.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('logistics.payouts.export') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
    </div>
</div>

<form method="GET" action="{{ route('logistics.payouts.index') }}" class="filter-bar mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search rider</label>
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Name, email, phone">
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                <option value="pending_approval" @selected($status === 'pending_approval')>Pending approval</option>
                <option value="approved" @selected($status === 'approved')>Approved</option>
                <option value="processing" @selected($status === 'processing')>Processing</option>
                <option value="completed" @selected($status === 'completed')>Completed</option>
                <option value="failed" @selected($status === 'failed')>Failed</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Rider</label>
            <select name="rider_id" class="form-select">
                <option value="">All riders</option>
                @foreach($riders as $rider)
                    <option value="{{ $rider->id }}" @selected((int) $riderId === (int) $rider->id)>{{ $rider->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-juvo w-100" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('logistics.payouts.index') }}">Reset</a>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Earnings</div><div class="stat-value">TZS {{ number_format($totals['earnings'], 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Available</div><div class="stat-value text-success">TZS {{ number_format($totals['available'], 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Pending approval</div><div class="stat-value text-warning">TZS {{ number_format($totals['pending'], 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Completed</div><div class="stat-value text-success">TZS {{ number_format($totals['completed'], 2) }}</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Rider balances</h6>
        <span class="text-muted small">{{ $riders->total() }} riders</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Rider</th><th>Gross earnings</th><th>Pending earnings</th><th>Available</th><th>Open payouts</th><th>Completed</th><th></th></tr></thead>
            <tbody>
                @forelse($stats as $stat)
                    @php $overview = $stat['balance']; @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $stat['rider']->full_name }}</div>
                            <div class="text-muted small">{{ $stat['rider']->email }}</div>
                        </td>
                        <td>TZS {{ number_format($overview['gross_earnings'], 2) }}</td>
                        <td>TZS {{ number_format($overview['pending_earnings'], 2) }}</td>
                        <td class="fw-semibold text-success">TZS {{ number_format($overview['available'], 2) }}</td>
                        <td>TZS {{ number_format($overview['open'], 2) }}</td>
                        <td>TZS {{ number_format($overview['completed'], 2) }}</td>
                        <td class="text-end">
                            <a href="{{ route('logistics.payouts.rider', $stat['rider']) }}" class="btn btn-sm btn-outline-secondary">Statement</a>
                            <button class="btn btn-sm btn-juvo" data-bs-toggle="modal" data-bs-target="#createPayout{{ $stat['rider']->id }}">Payout</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state"><strong>No riders found</strong><p>Active riders will appear here.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $riders->links() }}</div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Pending payouts</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Reference</th><th>Rider</th><th>Amount</th><th>Status</th><th>Initiated by</th><th></th></tr></thead>
                    <tbody>
                        @forelse($pending as $payout)
                            <tr>
                                <td><a href="{{ route('logistics.payouts.show', $payout) }}">{{ $payout->reference }}</a></td>
                                <td>{{ $payout->rider?->full_name }}</td>
                                <td>TZS {{ number_format((float) $payout->amount, 2) }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($payout->status)) }}</td>
                                <td>{{ $payout->initiated_by_name }}</td>
                                <td class="text-end">
                                    <a href="{{ route('logistics.payouts.show', $payout) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state py-3">No pending payouts.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recent payouts</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Reference</th><th>Rider</th><th>Amount</th><th>Status</th><th>Completed</th><th></th></tr></thead>
                    <tbody>
                        @forelse($recent as $payout)
                            <tr>
                                <td><a href="{{ route('logistics.payouts.show', $payout) }}">{{ $payout->reference }}</a></td>
                                <td>{{ $payout->rider?->full_name }}</td>
                                <td>TZS {{ number_format((float) $payout->amount, 2) }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($payout->status)) }}</td>
                                <td>{{ optional($payout->completed_at)->format('d M Y H:i') ?: '—' }}</td>
                                <td class="text-end"><a href="{{ route('logistics.payouts.show', $payout) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state py-3">No recent payouts.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@foreach($stats as $stat)
    <div class="modal fade" id="createPayout{{ $stat['rider']->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('logistics.payouts.store', $stat['rider']) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create payout for {{ $stat['rider']->full_name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Available balance: TZS {{ number_format($stat['balance']['available'], 2) }}</p>
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
                            <input type="text" name="account_name" value="{{ $stat['rider']->bank_account_name }}" class="form-control" maxlength="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account number</label>
                            <input type="text" name="account_number" value="{{ $stat['rider']->bank_account_number }}" class="form-control" maxlength="64">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bank name</label>
                            <input type="text" name="bank_name" value="{{ $stat['rider']->bank_name }}" class="form-control" maxlength="80">
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
@endforeach
@endsection
