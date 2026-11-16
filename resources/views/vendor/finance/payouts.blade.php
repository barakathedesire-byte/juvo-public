@extends('vendor.layouts.master')

@section('content')
<div class="container mt-4">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php $sym = activeCurrency()->symbol; @endphp

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card p-3">
                <small class="text-muted">Available</small>
                <div class="fw-semibold">{{ $sym }}{{ number_format($balances['available'], 2) }}</div>
                <small class="text-muted">Withdrawable: {{ $sym }}{{ number_format($balances['withdrawable'], 2) }}</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <small class="text-muted">Reserved (pending review)</small>
                <div class="fw-semibold">{{ $sym }}{{ number_format($balances['reserved'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <small class="text-muted">Minimum payout</small>
                <div class="fw-semibold">{{ $sym }}{{ number_format($minimumPayout, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header card-header-bg text-white">Request payout</div>
        <div class="card-body">
            <form action="{{ route('vendor.finance.payouts.request') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="{{ $minimumPayout > 0 ? number_format($minimumPayout, 2, '.', '') : '0.01' }}" max="{{ number_format($balances['withdrawable'], 2, '.', '') }}" class="form-control" name="amount" required placeholder="{{ number_format(max($minimumPayout, $balances['withdrawable'] > 0 ? $minimumPayout : 0), 2) }}">
                    <small class="text-muted">Max: {{ $sym }}{{ number_format($balances['withdrawable'], 2) }}</small>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Payout method (optional)</label>
                    <select class="form-select" name="payout_method_id">
                        <option value="">— No preferred method —</option>
                        @foreach ($payoutMethods as $m)
                            <option value="{{ $m->id }}">
                                {{ ucfirst($m->method) }}{{ $m->provider ? ' – '.$m->provider : '' }} ({{ $m->account_name }})
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Add methods in Settings → Store</small>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" {{ $balances['withdrawable'] <= 0 ? 'disabled' : '' }}>
                        <i class="fas fa-paper-plane me-1"></i> Submit request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header card-header-bg text-white">Payout history</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Paid</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $payouts = auth('vendor')->user()->payouts()->with('payoutMethod')->latest()->get(); @endphp
                        @forelse ($payouts as $p)
                            <tr>
                                <td>{{ $p->id }}</td>
                                <td>{{ $p->reference }}</td>
                                <td>{{ $sym }}{{ number_format((float) $p->amount, 2) }}</td>
                                <td>{{ $p->method ?? optional($p->payoutMethod)->method ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $p->status === 'paid' ? 'success' : ($p->status === 'rejected' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($p->status) }}
                                    </span>
                                </td>
                                <td>{{ $p->requested_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $p->paid_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $p->rejection_reason ?? $p->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">No payouts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
