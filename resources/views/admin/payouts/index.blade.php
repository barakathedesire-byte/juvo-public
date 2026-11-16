@extends('admin.layouts.admin')

@section('title', 'Vendor payouts')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Vendor payouts &amp; commissions</h4>
    </div>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger mt-3">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3 mt-2">
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Gross sales</small><h4 class="mb-0">{{ number_format($totals['gross_sales'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Commission earned</small><h4 class="mb-0">{{ number_format($totals['commission'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Payable now</small><h4 class="mb-0">{{ number_format($totals['payable'], 2) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Paid out</small><h4 class="mb-0">{{ number_format($totals['paid_out'], 2) }}</h4></div></div></div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header">Vendor balances</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Vendor</th><th class="text-end">Gross sales</th><th class="text-end">Commission</th><th class="text-end">Payable</th><th class="text-end">Paid out</th><th class="text-end">Create payout</th></tr></thead>
                <tbody>
                    @forelse($vendors as $vendor)
                        <tr>
                            <td>{{ $vendor->name }} <small class="text-muted">#{{ $vendor->id }} · {{ $vendor->effectiveCommissionRate() }}%</small></td>
                            <td class="text-end">{{ number_format($vendor->gross_sales, 2) }}</td>
                            <td class="text-end">{{ number_format($vendor->commission_owed, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($vendor->payable, 2) }}</td>
                            <td class="text-end">{{ number_format($vendor->paid_out, 2) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.payouts.store') }}" class="d-flex gap-1 justify-content-end">
                                    @csrf
                                    <input type="hidden" name="vendor_id" value="{{ $vendor->id }}">
                                    <input name="amount" type="number" step="0.01" min="0.01" value="{{ $vendor->payable > 0 ? number_format($vendor->payable, 2, '.', '') : '' }}" class="form-control form-control-sm" style="width:120px" placeholder="Amount">
                                    <input name="method" class="form-control form-control-sm" style="width:100px" placeholder="Method">
                                    <button class="btn btn-sm btn-primary">Pay</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No vendors yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mt-3 mb-5">
        <div class="card-header">Payout history</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Reference</th><th>Vendor</th><th class="text-end">Amount</th><th>Method</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($payouts as $payout)
                        <tr>
                            <td>{{ $payout->reference }}</td>
                            <td>{{ $payout->vendor->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</td>
                            <td>{{ $payout->method ?? '—' }}</td>
                            <td><span class="badge bg-{{ $payout->status === 'paid' ? 'success' : ($payout->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($payout->status) }}</span></td>
                            <td>{{ $payout->created_at->format('Y-m-d') }}</td>
                            <td class="text-end">
                                @if(! in_array($payout->status, ['paid', 'rejected']))
                                    <form method="POST" action="{{ route('admin.payouts.pay', $payout->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Mark paid</button></form>
                                    <form method="POST" action="{{ route('admin.payouts.reject', $payout->id) }}" class="d-inline" onsubmit="return confirm('Reject this payout?')">@csrf<button class="btn btn-sm btn-outline-danger">Reject</button></form>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No payouts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex justify-content-center mb-5">{{ $payouts->links() }}</div>
@endsection
