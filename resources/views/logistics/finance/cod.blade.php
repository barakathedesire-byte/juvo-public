@extends('logistics.layouts.app')

@section('title', 'Cash & COD Reconciliation')
@section('heading', 'Cash & COD Reconciliation')
@section('breadcrumb')
    <span>Logistics</span>
    <span aria-hidden="true">/</span>
    <span>Finance</span>
    <span aria-hidden="true">/</span>
    <span>Cash &amp; COD</span>
@endsection

@section('content')
<div class="page-header">
    <div>
        <span class="page-eyebrow">Finance</span>
        <h2>Cash &amp; COD reconciliation</h2>
        <p>Track collected cash, verify rider handovers, reconcile partial remittances, and manage discrepancy adjustments.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('logistics.finance.export') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
        @if(auth('logistics_admin')->user()?->canAccessLogisticsModule('finance'))
            <button type="button" class="btn btn-juvo btn-sm" data-bs-toggle="modal" data-bs-target="#handoverModal"><i class="fas fa-hand-holding-dollar me-1"></i>New handover</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#adjustmentModal"><i class="fas fa-scale-balanced me-1"></i>New adjustment</button>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('logistics.finance.index') }}" class="filter-bar mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Search</label>
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Order or tracking">
        </div>
        <div class="col-md-2">
            <label class="form-label">Rider</label>
            <select name="rider_id" class="form-select">
                <option value="">All riders</option>
                @foreach($riders as $rider)
                    <option value="{{ $rider->id }}" @selected((int) $riderId === (int) $rider->id)>{{ $rider->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Zone</label>
            <select name="zone" class="form-select">
                <option value="">All zones</option>
                @foreach($zones as $zoneOption)
                    <option value="{{ $zoneOption }}" @selected($zone === $zoneOption)>{{ $zoneOption }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                <option value="awaiting_collection" @selected($status === 'awaiting_collection')>Awaiting collection</option>
                <option value="outstanding" @selected($status === 'outstanding')>Outstanding</option>
                <option value="pending_verification" @selected($status === 'pending_verification')>Pending verification</option>
                <option value="reconciled" @selected($status === 'reconciled')>Reconciled</option>
                <option value="disputed" @selected($status === 'disputed')>Disputed</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">From</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
        </div>
        <div class="col-md-1">
            <label class="form-label">To</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-juvo w-100" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('logistics.finance.index') }}">Reset</a>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Expected COD</div>
            <div class="stat-value">TZS {{ number_format($totals['expected'], 2) }}</div>
            <div class="text-muted small mt-1">Delivered COD orders</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Collected cash</div>
            <div class="stat-value text-success">TZS {{ number_format($totals['collected'], 2) }}</div>
            <div class="text-muted small mt-1">{{ $transactions->total() }} delivered orders</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Outstanding</div>
            <div class="stat-value text-warning">TZS {{ number_format($totals['outstanding'], 2) }}</div>
            <div class="text-muted small mt-1">{{ $totals['outstanding_count'] }} orders awaiting remittance</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Reconciled 30 days</div>
            <div class="stat-value text-success">TZS {{ number_format($totals['reconciled_30d'], 2) }}</div>
            <div class="text-muted small mt-1">{{ $totals['pending_handovers'] }} pending handovers</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">COD transactions</h6>
                <span class="text-muted small">{{ $transactions->total() }} records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Rider</th>
                            <th>Expected</th>
                            <th>Collected</th>
                            <th>Remitted</th>
                            <th>Adjustments</th>
                            <th>Outstanding</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $order)
                            @php
                                $expected = (float) ($order->cod_amount ?? $order->total_amount);
                                $remitted = (float) ($order->cod_remitted_amount ?? 0);
                                $adjustments = (float) $order->approvedCodAdjustmentNet();
                                $outstanding = max(0.0, $expected - $remitted - $adjustments);
                                $isReconciled = $outstanding <= 0.01 && $order->cod_collected_at;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">#{{ $order->id }}</div>
                                    <div class="text-muted small">{{ $order->logistics_tracking_id }}</div>
                                </td>
                                <td>{{ $order->assignedRider?->full_name ?? 'Unassigned' }}</td>
                                <td>TZS {{ number_format($expected, 2) }}</td>
                                <td>{{ $order->cod_collected_at ? 'TZS '.number_format((float) ($order->cod_amount ?? $order->total_amount), 2) : 'Not collected' }}</td>
                                <td>TZS {{ number_format($remitted, 2) }}</td>
                                <td class="{{ $adjustments >= 0 ? 'text-success' : 'text-danger' }}">TZS {{ number_format($adjustments, 2) }}</td>
                                <td class="{{ $outstanding > 0 ? 'text-warning fw-semibold' : 'text-success' }}">TZS {{ number_format($outstanding, 2) }}</td>
                                <td>
                                    @if(!$order->cod_collected_at)
                                        <span class="badge-status status-pending">Awaiting collection</span>
                                    @elseif($isReconciled)
                                        <span class="badge-status status-delivered">Reconciled</span>
                                    @elseif($order->codHandovers->contains(fn ($allocation) => $allocation->handover?->status === 'pending_verification'))
                                        <span class="badge-status status-under_review">Pending verification</span>
                                    @else
                                        <span class="badge-status status-in_transit">Outstanding</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($order->assignedRider)
                                        <a href="{{ route('logistics.finance.rider', $order->assignedRider) }}" class="btn btn-sm btn-outline-secondary">Rider</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="empty-state"><strong>No COD transactions found</strong><p>Delivered COD orders will appear here.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $transactions->links() }}</div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Awaiting collection</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Order</th><th>Rider</th><th>Amount</th><th>Delivered</th></tr></thead>
                        <tbody>
                            @forelse($awaitingCollection as $order)
                                <tr>
                                    <td>#{{ $order->id }}</td>
                                    <td>{{ $order->assignedRider?->full_name ?? 'Unassigned' }}</td>
                                    <td>TZS {{ number_format((float) ($order->cod_amount ?? $order->total_amount), 2) }}</td>
                                    <td>{{ optional($order->delivered_at)->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-state py-3">No delivered COD orders awaiting collection.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Outstanding by rider</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Rider</th><th>Collected</th><th>Remitted</th><th>Adjustments</th><th>Outstanding</th></tr></thead>
                        <tbody>
                            @forelse($byRider as $row)
                                <tr>
                                    <td><a href="{{ route('logistics.finance.rider', $row->rider) }}">{{ $row->rider->full_name }}</a></td>
                                    <td>TZS {{ number_format($row->collected, 2) }}</td>
                                    <td>TZS {{ number_format($row->remitted, 2) }}</td>
                                    <td>TZS {{ number_format($row->adjustments, 2) }}</td>
                                    <td class="fw-semibold text-warning">TZS {{ number_format($row->outstanding, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state py-3">No outstanding COD balances.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Pending handovers</h6>
                <span class="badge bg-warning text-dark">{{ $pendingHandovers->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Reference</th><th>Rider</th><th>Amount</th><th>Method</th><th>Submitted</th><th></th></tr></thead>
                        <tbody>
                            @forelse($pendingHandovers as $handover)
                                <tr>
                                    <td>{{ $handover->reference }}</td>
                                    <td>{{ $handover->rider?->full_name }}</td>
                                    <td>TZS {{ number_format((float) $handover->amount, 2) }}</td>
                                    <td>{{ str_replace('_', ' ', ucfirst($handover->method)) }}</td>
                                    <td>{{ optional($handover->created_at)->format('d M Y H:i') }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#verifyHandover{{ $handover->id }}">Verify</button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectHandover{{ $handover->id }}">Reject</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state py-3">No handovers awaiting verification.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Pending adjustments</h6>
                <span class="badge bg-warning text-dark">{{ $pendingAdjustments->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Reference</th><th>Type</th><th>Direction</th><th>Amount</th><th>Reason</th><th></th></tr></thead>
                        <tbody>
                            @forelse($pendingAdjustments as $adjustment)
                                <tr>
                                    <td>{{ $adjustment->reference }}</td>
                                    <td>{{ str_replace('_', ' ', ucfirst($adjustment->type)) }}</td>
                                    <td>{{ ucfirst($adjustment->direction) }}</td>
                                    <td>TZS {{ number_format((float) $adjustment->amount, 2) }}</td>
                                    <td class="text-truncate" style="max-width: 180px;">{{ $adjustment->reason }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('logistics.finance.adjustments.approve', $adjustment) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectAdjustment{{ $adjustment->id }}">Reject</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state py-3">No adjustments awaiting approval.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Recent handovers</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Reference</th><th>Rider</th><th>Amount</th><th>Method</th><th>Status</th><th>Initiated by</th><th>Verified by</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse($recentlyReconciled as $handover)
                        <tr>
                            <td>{{ $handover->reference }}</td>
                            <td>{{ $handover->rider?->full_name }}</td>
                            <td>TZS {{ number_format((float) $handover->amount, 2) }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($handover->method)) }}</td>
                            <td><span class="badge-status status-{{ $handover->status === 'verified' ? 'delivered' : ($handover->status === 'rejected' ? 'rejected' : 'pending') }}">{{ str_replace('_', ' ', ucfirst($handover->status)) }}</span></td>
                            <td>{{ $handover->initiated_by_name }}</td>
                            <td>{{ $handover->verified_by_name ?: '—' }}</td>
                            <td>{{ optional($handover->created_at)->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state py-3">No handovers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@php
    $handoverRiders = $riders;
@endphp

<div class="modal fade" id="handoverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('logistics.finance.handovers.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Create cash handover</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Rider</label>
                        <select name="rider_id" class="form-select" required>
                            <option value="">Select rider</option>
                            @foreach($handoverRiders as $rider)
                                <option value="{{ $rider->id }}">{{ $rider->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (TZS)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank transfer</option>
                            <option value="mobile_money">Mobile money</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">External reference</label>
                        <input type="text" name="external_reference" class="form-control" maxlength="120">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" maxlength="1000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-juvo">Create handover</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('logistics.finance.adjustments.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Create COD adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Order ID (optional)</label>
                        <input type="number" name="order_id" class="form-control" min="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rider (optional)</label>
                        <select name="rider_id" class="form-select">
                            <option value="">Select rider</option>
                            @foreach($riders as $rider)
                                <option value="{{ $rider->id }}">{{ $rider->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="discrepancy">Discrepancy</option>
                            <option value="write_off">Write-off</option>
                            <option value="refund">Refund</option>
                            <option value="correction">Correction</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Direction</label>
                        <select name="direction" class="form-select" required>
                            <option value="decrease">Decrease outstanding</option>
                            <option value="increase">Increase outstanding</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Amount (TZS)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="3" required maxlength="1000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-juvo">Create adjustment</button>
            </div>
        </form>
    </div>
</div>

@foreach($pendingHandovers as $handover)
    <div class="modal fade" id="verifyHandover{{ $handover->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('logistics.finance.handovers.verify', $handover) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Verify handover {{ $handover->reference }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Rider: {{ $handover->rider?->full_name }} · Amount: TZS {{ number_format((float) $handover->amount, 2) }}</p>
                    <div class="mb-3">
                        <label class="form-label">External reference</label>
                        <input type="text" name="external_reference" value="{{ $handover->external_reference }}" class="form-control" maxlength="120">
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" maxlength="1000">{{ $handover->notes }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Verify and reconcile</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="rejectHandover{{ $handover->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('logistics.finance.handovers.reject', $handover) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject handover {{ $handover->reference }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Rejection reason</label>
                    <textarea name="rejection_reason" class="form-control" rows="4" required maxlength="1000"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject handover</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@foreach($pendingAdjustments as $adjustment)
    <div class="modal fade" id="rejectAdjustment{{ $adjustment->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('logistics.finance.adjustments.reject', $adjustment) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject adjustment {{ $adjustment->reference }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Rejection reason</label>
                    <textarea name="rejection_reason" class="form-control" rows="4" required maxlength="1000"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject adjustment</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
