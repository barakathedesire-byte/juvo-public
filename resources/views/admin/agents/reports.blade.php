@extends('admin.layouts.admin')

@section('content')
<div class="row g-3 mt-4">
    @php
        $cards = [
            ['Agents', $summary['agents']],
            ['Active', $summary['active']],
            ['Commission pending', number_format($summary['commission_pending'], 2)],
            ['Commission available', number_format($summary['commission_available'], 2)],
            ['Commission paid', number_format($summary['commission_paid'], 2)],
            ['Commission reversed', number_format($summary['commission_reversed'], 2)],
            ['Payouts awaiting', number_format($summary['payout_pending_amount'], 2)],
            ['Open fraud cases', $summary['fraud_open']],
        ];
    @endphp
    @foreach ($cards as [$label, $value])
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fw-bold" style="font-size:20px;">{{ $value }}</div></div></div>
        </div>
    @endforeach

    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Agent Performance</h6>
                <a href="{{ route('admin.sales-agents.index') }}" class="btn btn-sm btn-light">Back</a>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end mb-3">
                    <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></div>
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
                </form>

                <h6>Leaderboard (delivered volume)</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>#</th><th>Agent</th><th>Delivered orders</th><th>Volume</th></tr></thead>
                        <tbody>
                            @forelse ($leaderboard as $i => $row)
                                <tr><td>{{ $i + 1 }}</td><td>{{ $row['name'] }}</td><td>{{ $row['orders'] }}</td><td>{{ number_format($row['volume'], 2) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h6>Commission by role</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Role</th><th>Entries</th><th>Total</th></tr></thead>
                        <tbody>
                            @forelse ($commissionByRole as $row)
                                <tr><td>{{ $row->role }}</td><td>{{ $row->entries }}</td><td>{{ number_format((float) $row->total, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">No commissions.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h6>Recent payouts</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Reference</th><th>Agent</th><th>Amount</th><th>Status</th><th>Requested</th></tr></thead>
                        <tbody>
                            @forelse ($payouts as $payout)
                                <tr>
                                    <td>{{ $payout->reference }}</td>
                                    <td>{{ $payout->agent->name ?? '—' }}</td>
                                    <td>{{ number_format((float) $payout->amount, 2) }}</td>
                                    <td>{{ $payout->status }}</td>
                                    <td>{{ optional($payout->requested_at)->format('Y-m-d') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No payouts.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
