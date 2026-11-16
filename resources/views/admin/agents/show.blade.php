@extends('admin.layouts.admin')

@php
    $canVerify = auth()->user()?->can('sales_agents.verify');
    $canApprove = auth()->user()?->can('sales_agents.approve');
    $canSuspend = auth()->user()?->can('sales_agents.suspend');
    $operational = in_array($agent->status, \App\Models\SalesRep::OPERATIONAL_STATUSES, true);
@endphp

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">{{ $agent->name }} <span class="text-white-50">({{ $agent->code }})</span></h6>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.sales-agents.index') }}" class="btn btn-sm btn-light">Back</a>
            @can('sales_agents.manage')
                <a href="{{ route('admin.sales-agents.edit', $agent->id) }}" class="btn btn-sm btn-light">Edit</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="row g-3">
            <div class="col-md-4 col-xl-2"><div class="border rounded p-2"><div class="text-muted small">Status</div><div class="fw-bold">{{ ucfirst($agent->status) }}</div></div></div>
            <div class="col-md-4 col-xl-2"><div class="border rounded p-2"><div class="text-muted small">Verification</div><div class="fw-bold">{{ ucfirst($agent->verification_status) }}</div></div></div>
            <div class="col-md-4 col-xl-2"><div class="border rounded p-2"><div class="text-muted small">Commission</div><div class="fw-bold">{{ $agent->commission_rate }}%</div></div></div>
            <div class="col-md-4 col-xl-2"><div class="border rounded p-2"><div class="text-muted small">Available</div><div class="fw-bold">{{ number_format($balance['available'], 2) }}</div></div></div>
            <div class="col-md-4 col-xl-2"><div class="border rounded p-2"><div class="text-muted small">Pending</div><div class="fw-bold">{{ number_format($balance['pending'], 2) }}</div></div></div>
            <div class="col-md-4 col-xl-2"><div class="border rounded p-2"><div class="text-muted small">Paid</div><div class="fw-bold">{{ number_format($balance['paid'], 2) }}</div></div></div>
        </div>

        <hr>

        <div class="row g-3">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th>Email</th><td>{{ $agent->email ?? '—' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $agent->phone ?? '—' }}</td></tr>
                    <tr><th>Region</th><td>{{ $agent->region->name ?? '—' }}</td></tr>
                    <tr><th>Sponsor</th><td>{{ $agent->sponsor->name ?? '—' }}</td></tr>
                    <tr><th>Staff user</th><td>{{ $agent->user->name ?? '—' }} {{ $agent->user_id ? '(#'.$agent->user_id.')' : '' }}</td></tr>
                    <tr><th>Applied</th><td>{{ optional($agent->applied_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
                    <tr><th>Approved</th><td>{{ optional($agent->approved_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Actions</h6>
                <div class="d-flex flex-wrap gap-2">
                    @if ($canVerify && in_array($agent->status, ['draft', 'applied'], true))
                        <form method="POST" action="{{ route('admin.sales-agents.submitVerification', $agent->id) }}">@csrf<button class="btn btn-sm btn-outline-info">Submit for verification</button></form>
                    @endif
                    @if ($canVerify && $agent->status === 'verification')
                        <form method="POST" action="{{ route('admin.sales-agents.verify', $agent->id) }}" class="d-flex gap-1 align-items-center">
                            @csrf
                            <input type="hidden" name="passed" value="1">
                            <button class="btn btn-sm btn-outline-success">Verify passed</button>
                        </form>
                        <form method="POST" action="{{ route('admin.sales-agents.verify', $agent->id) }}" class="d-flex gap-1 align-items-center">
                            @csrf
                            <input type="hidden" name="passed" value="0">
                            <button class="btn btn-sm btn-outline-danger">Verify failed</button>
                        </form>
                        <form method="POST" action="{{ route('admin.sales-agents.startReview', $agent->id) }}">@csrf<button class="btn btn-sm btn-outline-info">Start review</button></form>
                    @endif
                    @if ($canApprove && $agent->status === 'review')
                        <form method="POST" action="{{ route('admin.sales-agents.approve', $agent->id) }}">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                        <form method="POST" action="{{ route('admin.sales-agents.reject', $agent->id) }}" class="d-flex gap-1">
                            @csrf
                            <input name="reason" class="form-control form-control-sm" placeholder="Reject reason" required>
                            <button class="btn btn-sm btn-danger">Reject</button>
                        </form>
                    @endif
                    @if ($canSuspend && ! in_array($agent->status, ['suspended', 'closed', 'rejected'], true))
                        <form method="POST" action="{{ route('admin.sales-agents.suspend', $agent->id) }}" class="d-flex gap-1">
                            @csrf
                            <input name="reason" class="form-control form-control-sm" placeholder="Suspend reason" required>
                            <button class="btn btn-sm btn-warning">Suspend</button>
                        </form>
                    @endif
                    @if ($canSuspend && $agent->status === 'suspended')
                        <form method="POST" action="{{ route('admin.sales-agents.reinstate', $agent->id) }}">@csrf<button class="btn btn-sm btn-outline-success">Reinstate</button></form>
                    @endif
                    @if ($canSuspend && $agent->status !== 'closed')
                        <form method="POST" action="{{ route('admin.sales-agents.close', $agent->id) }}">@csrf<button class="btn btn-sm btn-outline-dark">Close</button></form>
                    @endif
                </div>
                @if ($policy)
                    <div class="text-muted small mt-2">Active policy: <strong>{{ $policy->name }}</strong> v{{ $policy->version }} ({{ $policy->rate }}% {{ $policy->basis }})</div>
                @else
                    <div class="text-danger small mt-2">No active commission policy — commissions will not accrue.</div>
                @endif

                @can('sales_agents.payout')
                    <div class="mt-3">
                        <div class="text-muted small">Payout account: <strong>{{ ucfirst($agent->payout_status ?? 'unset') }}</strong></div>
                        <form method="POST" action="{{ route('admin.sales-agents.payoutAccount', $agent->id) }}" class="d-flex gap-1 mt-1">
                            @csrf
                            <select name="status" class="form-select form-select-sm" style="max-width:150px;">
                                <option value="verified" @selected($agent->payout_status === 'verified')>Verified</option>
                                <option value="pending" @selected($agent->payout_status === 'pending')>Pending</option>
                                <option value="rejected" @selected($agent->payout_status === 'rejected')>Rejected</option>
                            </select>
                            <button class="btn btn-sm btn-outline-secondary">Update payout account</button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <hr>

        <h6>Recent commission entries</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead><tr><th>#</th><th>Order</th><th>Role</th><th>Basis</th><th>Rate</th><th>Amount</th><th>State</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($agent->commissionEntries as $entry)
                        <tr>
                            <td>{{ $entry->id }}</td>
                            <td>{{ $entry->order_id }}</td>
                            <td>{{ $entry->role }}</td>
                            <td>{{ number_format((float) $entry->basis_amount, 2) }}</td>
                            <td>{{ $entry->rate }}%</td>
                            <td>{{ number_format((float) $entry->amount, 2) }}</td>
                            <td>{{ $entry->state }}</td>
                            <td>{{ $entry->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h6 class="mt-3">Recent payouts</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead><tr><th>Reference</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th></tr></thead>
                <tbody>
                    @forelse ($agent->payoutRequests as $payout)
                        <tr>
                            <td>{{ $payout->reference }}</td>
                            <td>{{ number_format((float) $payout->amount, 2) }}</td>
                            <td>{{ $payout->method }}</td>
                            <td>{{ $payout->status }}</td>
                            <td>{{ $payout->requested_at?->format('Y-m-d') ?? $payout->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No payouts.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h6 class="mt-3">Status history</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead><tr><th>From</th><th>To</th><th>Reason</th><th>By</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($agent->statusHistories as $history)
                        <tr>
                            <td>{{ $history->from_status ?? '—' }}</td>
                            <td>{{ $history->to_status }}</td>
                            <td>{{ $history->reason ?? '—' }}</td>
                            <td>{{ $history->actor->name ?? '—' }}</td>
                            <td>{{ $history->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No history.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
