@extends('admin.layouts.admin')

@section('title', 'Finance Command Center')

@section('content')
@php($money = fn ($v) => number_format((float) $v, 2))

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h4 class="mb-0">Finance Command Center</h4>
    <div>
        <a href="{{ route('admin.finance.ledger') }}" class="btn btn-outline-secondary btn-sm">Ledger</a>
        <a href="{{ route('admin.finance.reconciliation') }}" class="btn btn-outline-primary btn-sm">Reconciliation</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Platform revenue (commission)</div>
                <div class="fs-4 fw-semibold">{{ $money($kpis['revenue']) }}</div>
                <div class="text-muted small">Gross {{ $money($kpis['gross']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Vendor payable</div>
                <div class="fs-4 fw-semibold">{{ $money($kpis['payable']) }}</div>
                <div class="text-muted small">Pending earnings {{ $money($kpis['pending_earnings']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Paid out</div>
                <div class="fs-4 fw-semibold">{{ $money($kpis['paid_out']) }}</div>
                <div class="text-muted small">Settled {{ $money($kpis['settled']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Pending payouts</div>
                <div class="fs-4 fw-semibold">{{ $kpis['pending_payout_count'] }}</div>
                <div class="text-muted small">{{ $money($kpis['pending_payout_amount']) }} awaiting decision</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Adjustments posted</div>
                <div class="fs-5 fw-semibold">{{ $money($kpis['adjustments']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Reversed earnings</div>
                <div class="fs-5 fw-semibold">{{ $money($kpis['reversed']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Open refunds</div>
                <div class="fs-5 fw-semibold">{{ $kpis['open_refund_count'] }}</div>
                <div class="text-muted small">{{ $money($kpis['open_refund_amount']) }} requested</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Top vendor balances</h6></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Vendor</th><th class="text-end">Withdrawable</th><th class="text-end">Reserved</th></tr></thead>
                    <tbody>
                        @forelse($topVendors as $row)
                            <tr>
                                <td>{{ $row['vendor']->name }}</td>
                                <td class="text-end">{{ $money($row['withdrawable']) }}</td>
                                <td class="text-end">{{ $money($row['reserved']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No vendor balances yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent payouts</h6>
                <a href="{{ route('admin.payouts.index') }}" class="small">Manage</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Reference</th><th>Vendor</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($recentPayouts as $payout)
                            <tr>
                                <td>{{ $payout->reference }}</td>
                                <td>{{ $payout->vendor?->name ?? '—' }}</td>
                                <td class="text-end">{{ $money($payout->amount) }}</td>
                                <td>{{ $payout->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No payouts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Recent ledger activity</h6>
        <a href="{{ route('admin.finance.ledger') }}" class="small">Full ledger</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>When</th><th>Vendor</th><th>Type</th><th class="text-end">Amount</th><th>Description</th></tr></thead>
            <tbody>
                @forelse($recentTransactions as $txn)
                    <tr>
                        <td>{{ $txn->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $txn->vendor?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-light">{{ $txn->type }}</span></td>
                        <td class="text-end">{{ $money($txn->amount) }}</td>
                        <td>{{ $txn->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
