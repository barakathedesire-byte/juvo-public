@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-coins me-2" style="color:#f68b1e;"></i>Advertising Finances</h4>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small text-uppercase">Funded</div>
                <div class="fs-4 fw-semibold">{{ \App\Support\Ads\Money::format($summary['funded']) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small text-uppercase">Spent</div>
                <div class="card-title h4 mb-0">{{ \App\Support\Ads\Money::format($summary['spent']) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small text-uppercase">Reserved (held)</div>
                <div class="card-title h4 mb-0">{{ \App\Support\Ads\Money::format($summary['reserved']) }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Vendor advertising accounts</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Vendor</th><th>Status</th><th class="text-end">Funded</th>
                        <th class="text-end">Spent</th><th class="text-end">Reserved</th><th class="text-end">Available</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($accounts as $a)
                        <tr>
                            <td>{{ $a->id }}</td>
                            <td class="fw-semibold">{{ $a->vendor->name ?? $a->vendor->email ?? '—' }}</td>
                            <td><span class="badge {{ $a->isActive() ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $a->status }}</span></td>
                            <td class="text-end">{{ number_format($a->funded_minor / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($a->spent_minor / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($a->reserved_minor / 100, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($a->available_minor / 100, 2) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.ad-manager.accounts.credit', $a->id) }}" class="d-flex gap-1 justify-content-end">
                                    @csrf
                                    <input name="amount" class="form-control form-control-sm" style="width:90px" placeholder="Amount" type="number" step="0.01" min="0.01">
                                    <input name="reason" class="form-control form-control-sm" placeholder="Reason" style="width:120px">
                                    <button class="btn btn-sm btn-outline-primary">Credit</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No advertising accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $accounts->links() }}</div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Ledger (append-only)</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>When</th><th>Type</th><th>Dir</th><th class="text-end">Amount</th>
                        <th>Campaign</th><th>Reference</th><th class="text-end">Balance</th></tr>
                </thead>
                <tbody>
                    @forelse($ledger as $e)
                        <tr>
                            <td>{{ $e->id }}</td>
                            <td>{{ $e->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $e->type }}</td>
                            <td><span class="badge {{ $e->direction === 'credit' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $e->direction }}</span></td>
                            <td class="text-end">{{ number_format($e->amount_minor / 100, 2) }}</td>
                            <td>{{ $e->campaign->title ?? '—' }}</td>
                            <td><code class="small">{{ \Illuminate\Support\Str::limit($e->reference, 32) }}</code></td>
                            <td class="text-end">{{ number_format($e->balance_after_minor / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No ledger entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $ledger->links() }}</div>
    </div>
@endsection
