@extends('agent.layouts.agent')

@section('title', 'Commissions & Bonuses')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">Commissions & Bonuses</h2>
            <p class="jt-muted mb-0">Commission calculations and eligibility only. No payouts or financial settlement.</p>
        </div>
        <a href="{{ route('agent.commissions.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-sm btn-outline-secondary">Export CSV</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="GET" action="{{ route('agent.commissions.index') }}" class="jt-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-2">
            <input type="date" name="from" value="{{ request('from', $start->format('Y-m-d')) }}" class="form-control">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" value="{{ request('to', $end->format('Y-m-d')) }}" class="form-control">
        </div>
        <div class="col-md-2">
            <select name="state" class="form-select">
                <option value="">All states</option>
                @foreach ($states as $state)
                    <option value="{{ $state }}" @selected(($filters['state'] ?? '') === $state)>{{ $state }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="role" class="form-select">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-outline-dark w-100">Filter</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    @foreach ([
        ['Total calculated', $metrics['total']],
        ['Eligible', $metrics['eligible']],
        ['Pending validation', $metrics['pending']],
        ['Disqualified', $metrics['disqualified']],
        ['Bonuses', $metrics['bonuses']],
        ['Adjustments', $metrics['adjusted']],
        ['Available balance', $balance['available']],
        ['Pending balance', $balance['pending']],
    ] as $card)
        <div class="col-6 col-md-3">
            <div class="jt-card p-3 h-100">
                <div class="jt-muted" style="font-size:12px;">{{ $card[0] }}</div>
                <div class="fw-bold" style="font-size:20px;">{{ number_format((float) $card[1], 2) }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Commission History</h5>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>#</th><th>Order</th><th>Role</th><th>Basis</th><th>Rate</th><th>Amount</th><th>State</th><th>Date</th><th></th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ $entry->id }}</td>
                        <td>#{{ $entry->order_id }}</td>
                        <td>{{ $entry->role }}</td>
                        <td>{{ number_format((float) $entry->basis_amount, 2) }}</td>
                        <td>{{ $entry->rate }}</td>
                        <td class="{{ (float) $entry->amount < 0 ? 'text-danger' : '' }}">{{ number_format((float) $entry->amount, 2) }}</td>
                        <td><span class="badge bg-secondary">{{ $entry->state }}</span></td>
                        <td>{{ $entry->created_at?->format('Y-m-d') }}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#detail{{ $entry->id }}">Breakdown</button>
                        </td>
                    </tr>
                    <tr class="collapse" id="detail{{ $entry->id }}">
                        <td colspan="9">
                            <div class="border rounded p-2 bg-light">
                                <div><strong>Policy:</strong> {{ $entry->policy?->policy_key ?? '—' }} (v{{ $entry->policy_version }})</div>
                                <div><strong>Basis:</strong> {{ $entry->basis }}</div>
                                <div><strong>Hold reason:</strong> {{ $entry->hold_reason ?: '—' }}</div>
                                <div><strong>Notes:</strong> {{ $entry->notes ?: '—' }}</div>
                                <div><strong>Eligible at:</strong> {{ $entry->eligible_at?->format('d M Y H:i') ?: '—' }}</div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted text-center">No entries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $entries->links() }}</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Applicable Commission Rule</h5>
            @if ($policy)
                <div><strong>Type:</strong> {{ $policy->type ?? '—' }}</div>
                <div><strong>Rate:</strong> {{ $policy->rate }}</div>
                <div><strong>Basis:</strong> {{ $policy->basis }}</div>
                <div><strong>Effective:</strong> {{ $policy->effective_from?->format('d M Y') ?: '—' }}</div>
                <div><strong>Expiry:</strong> {{ $policy->effective_to?->format('d M Y') ?: '—' }}</div>
                <div><strong>Team enabled:</strong> {{ $policy->team_enabled ? 'Yes' : 'No' }}</div>
            @else
                <div class="jt-muted">No active commission policy found.</div>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Bonuses & Incentives</h5>
            <div class="jt-muted" style="font-size:12px;">Bonus entries from the commission ledger.</div>
            <table class="table table-sm mb-0">
                <thead><tr><th>Order</th><th>Amount</th><th>State</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($entries->where('role','bonus') as $bonus)
                        <tr>
                            <td>#{{ $bonus->order_id }}</td>
                            <td>{{ number_format((float) $bonus->amount, 2) }}</td>
                            <td>{{ $bonus->state }}</td>
                            <td>{{ $bonus->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="jt-muted">No bonus entries in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Flag a Commission Issue</h5>
    <form method="POST" action="{{ route('agent.commissions.disputes.store') }}">
        @csrf
        <div class="row g-2">
            <div class="col-md-3">
                <select name="entry_id" class="form-select" required>
                    <option value="">Select commission entry</option>
                    @foreach ($entries as $entry)
                        <option value="{{ $entry->id }}">#{{ $entry->id }} — Order #{{ $entry->order_id }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select" required>
                    <option value="missing_order">Missing eligible order</option>
                    <option value="incorrect_rate">Incorrect commission rate</option>
                    <option value="wrong_attribution">Wrong salesperson attribution</option>
                    <option value="pending_too_long">Delivered order still pending</option>
                    <option value="return_recorded_wrong">Return/cancellation recorded incorrectly</option>
                    <option value="bonus_not_recognized">Bonus milestone not recognized</option>
                    <option value="other">Other calculation error</option>
                </select>
            </div>
            <div class="col-md-4">
                <textarea name="description" class="form-control" rows="2" required placeholder="Explain the issue"></textarea>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-danger w-100">Submit Dispute</button>
            </div>
        </div>
    </form>

    @if ($disputes->count())
        <div class="mt-3">
            <h6>Submitted Disputes</h6>
            @foreach ($disputes as $dispute)
                <div class="border rounded p-2 mb-2">
                    <strong>{{ $dispute->ticket_number }}</strong> — {{ $dispute->status }}
                    <div class="jt-muted" style="font-size:12px;">{{ $dispute->created_at?->format('d M Y H:i') }}</div>
                    <div>{{ $dispute->resolution_notes }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
