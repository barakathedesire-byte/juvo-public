@extends('logistics.layouts.app')

@section('title', 'Rider COD Statement')
@section('heading', 'Rider COD Statement')
@section('breadcrumb')
    <span>Logistics</span>
    <span aria-hidden="true">/</span>
    <span>Finance</span>
    <span aria-hidden="true">/</span>
    <span>{{ $rider->full_name }}</span>
@endsection

@section('content')
<div class="page-header">
    <div>
        <span class="page-eyebrow">Cash &amp; COD</span>
        <h2>{{ $rider->full_name }}</h2>
        <p>Collected COD, remitted amounts, adjustments, and handover history for this rider.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('logistics.finance.index') }}" class="btn btn-outline-secondary btn-sm">Back to finance</a>
        <a href="{{ route('logistics.payouts.rider', $rider) }}" class="btn btn-juvo btn-sm">Payouts</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Collected</div><div class="stat-value">TZS {{ number_format($collected, 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Remitted</div><div class="stat-value text-success">TZS {{ number_format($remitted, 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Adjustments</div><div class="stat-value">TZS {{ number_format($adjustments, 2) }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">Outstanding</div><div class="stat-value text-warning">TZS {{ number_format($outstanding, 2) }}</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">COD orders</h6></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order</th><th>Expected</th><th>Remitted</th><th>Adjustments</th><th>Outstanding</th><th>Collected at</th><th>Remitted at</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    @php
                        $expected = (float) ($order->cod_amount ?? $order->total_amount);
                        $remitted = (float) ($order->cod_remitted_amount ?? 0);
                        $adjustment = (float) $order->approvedCodAdjustmentNet();
                        $outstanding = max(0.0, $expected - $remitted - $adjustment);
                    @endphp
                    <tr>
                        <td>#{{ $order->id }} <span class="text-muted small">{{ $order->logistics_tracking_id }}</span></td>
                        <td>TZS {{ number_format($expected, 2) }}</td>
                        <td>TZS {{ number_format($remitted, 2) }}</td>
                        <td>TZS {{ number_format($adjustment, 2) }}</td>
                        <td class="{{ $outstanding > 0 ? 'text-warning fw-semibold' : 'text-success' }}">TZS {{ number_format($outstanding, 2) }}</td>
                        <td>{{ optional($order->cod_collected_at)->format('d M Y H:i') }}</td>
                        <td>{{ optional($order->cod_remitted_at)->format('d M Y H:i') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state"><strong>No COD orders</strong><p>This rider has no delivered COD transactions.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Handovers</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Reference</th><th>Amount</th><th>Method</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                        @forelse($handovers as $handover)
                            <tr>
                                <td>{{ $handover->reference }}</td>
                                <td>TZS {{ number_format((float) $handover->amount, 2) }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($handover->method)) }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($handover->status)) }}</td>
                                <td>{{ optional($handover->created_at)->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state py-3">No handovers.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Adjustments</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Reference</th><th>Type</th><th>Direction</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($adjustmentsList as $adjustment)
                            <tr>
                                <td>{{ $adjustment->reference }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($adjustment->type)) }}</td>
                                <td>{{ ucfirst($adjustment->direction) }}</td>
                                <td>TZS {{ number_format((float) $adjustment->amount, 2) }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($adjustment->status)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state py-3">No adjustments.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
