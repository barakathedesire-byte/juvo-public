@extends('vendor.layouts.master')

@section('content')
<div class="container mt-4">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0">Finance &amp; Earnings</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('vendor.finance.transactions') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list me-1"></i> Transactions
            </a>
            <a href="{{ route('vendor.finance.statement') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export statement
            </a>
            <a href="{{ route('vendor.finance.payouts') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-money-bill-transfer me-1"></i> Payouts
            </a>
        </div>
    </div>

    @php $sym = activeCurrency()->symbol; @endphp

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card p-3 h-100">
                <small class="text-muted">Available balance</small>
                <div class="jv-mini">{{ $sym }}{{ number_format($balances['available'], 2) }}</div>
                <small class="text-muted">Withdrawable: {{ $sym }}{{ number_format($balances['withdrawable'], 2) }}</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 h-100">
                <small class="text-muted">Pending earnings</small>
                <div class="jv-mini" style="color:#6c757d;">{{ $sym }}{{ number_format($balances['pending'], 2) }}</div>
                <small class="text-muted">Released when orders complete</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 h-100">
                <small class="text-muted">Paid out</small>
                <div class="jv-mini" style="color:#198754;">{{ $sym }}{{ number_format($balances['paid'], 2) }}</div>
                <small class="text-muted">Lifetime settled</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 h-100">
                <small class="text-muted">Reserved (in review)</small>
                <div class="jv-mini" style="color:#fd7e14;">{{ $sym }}{{ number_format($balances['reserved'], 2) }}</div>
                <small class="text-muted">Payouts pending approval</small>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <div class="card p-3">
                <small class="text-muted">Gross sales</small>
                <div class="fw-semibold">{{ $sym }}{{ number_format($balances['gross'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <small class="text-muted">Marketplace commission</small>
                <div class="fw-semibold">{{ $sym }}{{ number_format($balances['commission'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <small class="text-muted">Lifetime net earnings</small>
                <div class="fw-semibold">{{ $sym }}{{ number_format($balances['pending'] + $balances['available'] + $balances['paid'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-7">
            <div class="card p-3 h-100">
                <h6 class="mb-3">Recent transactions</h6>
                @if ($recentTransactions->isEmpty())
                    <p class="text-muted mb-0">No transactions yet. Earnings appear here as orders complete.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Date</th><th>Type</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
                            <tbody>
                                @foreach ($recentTransactions as $t)
                                    <tr>
                                        <td>{{ $t->created_at?->format('Y-m-d') }}</td>
                                        <td><span class="badge bg-secondary">{{ ucfirst($t->type) }}</span></td>
                                        <td>{{ $t->description }}</td>
                                        <td class="text-end {{ $t->amount < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $t->amount < 0 ? '-' : '+' }}{{ number_format(abs((float) $t->amount), 2) }}
                                        </td>
                                        <td class="text-end">{{ number_format((float) $t->balance_after, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-3 h-100">
                <h6 class="mb-3">Recent payouts</h6>
                @if ($recentPayouts->isEmpty())
                    <p class="text-muted mb-0">No payouts requested yet.</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach ($recentPayouts as $p)
                            <li class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span>
                                    <span class="badge bg-{{ $p->status === 'paid' ? 'success' : ($p->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($p->status) }}</span>
                                    <small class="text-muted ms-1">{{ $p->reference }}</small>
                                </span>
                                <span class="fw-semibold">{{ $sym }}{{ number_format((float) $p->amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
