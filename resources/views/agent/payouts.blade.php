@extends('agent.layouts.agent')

@section('content')
<div class="row g-3">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Request a payout</h6>
                <p class="text-muted small">Available balance: <strong>{{ number_format($balance['available'], 2) }}</strong></p>

                @if (! $agentProfile->canOperate())
                    <div class="alert alert-warning">Your account is not currently active.</div>
                @elseif ($agentProfile->payout_status !== 'verified')
                    <div class="alert alert-warning">Your payout account must be verified before you can request a payout. <a href="{{ route('agent.profile.index') }}">Update profile</a>.</div>
                @elseif ($hasOpenRequest)
                    <div class="alert alert-info">You already have an open payout request awaiting review.</div>
                @else
                    <form action="{{ route('agent.payouts.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Amount (blank = full available balance)</label>
                            <input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="{{ number_format($balance['available'], 2) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payout method</label>
                            <input name="method" class="form-control" value="{{ $agentProfile->payout_method }}" placeholder="mobile_money / bank">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note (optional)</label>
                            <input name="notes" class="form-control" maxlength="500">
                        </div>
                        <button class="btn btn-primary">Submit request</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Payout history</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Reference</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th></tr></thead>
                        <tbody>
                            @forelse ($payouts as $payout)
                                <tr>
                                    <td>{{ $payout->reference }}</td>
                                    <td>{{ number_format((float) $payout->amount, 2) }}</td>
                                    <td>{{ $payout->method ?? '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $payout->status }}</span></td>
                                    <td>{{ optional($payout->requested_at)->format('Y-m-d') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center">No payouts yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $payouts->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
