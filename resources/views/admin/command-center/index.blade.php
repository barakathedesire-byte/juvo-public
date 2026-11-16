@extends('admin.layouts.admin')

@section('title', 'Command Center')

@section('content')
@php($money = fn ($v) => number_format((float) $v, 2))

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <div>
        <h4 class="mb-0">Command Center</h4>
        <div class="text-muted small">
            {{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary btn-sm" href="{{ route('admin.command-center.intelligence') }}"><i class="fas fa-gauge-high me-1"></i> Open Intelligence Command Center</a>
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
            <button class="btn btn-outline-secondary btn-sm">Apply</button>
        </form>
    </div>
</div>

@if ($panels['commerce'])
<div class="row g-3">
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Orders</div>
            <div class="fs-4 fw-semibold">{{ number_format($overview['commerce']['orders']) }}</div>
            <div class="text-muted small">{{ $overview['commerce']['open_orders'] }} open now</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Revenue</div>
            <div class="fs-4 fw-semibold">{{ $money($overview['commerce']['revenue']) }}</div>
            <div class="text-muted small">Avg {{ $money($overview['commerce']['avg_order']) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Customers</div>
            <div class="fs-4 fw-semibold">{{ number_format($overview['commerce']['customers']) }}</div>
            <div class="text-muted small">New in range</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Vendors</div>
            <div class="fs-4 fw-semibold">{{ number_format($overview['commerce']['vendors']) }}</div>
            <div class="text-muted small">{{ $overview['commerce']['pending_vendors'] }} awaiting review</div>
        </div></div>
    </div>
</div>
@endif

<div class="row g-3 mt-1">
    @if ($panels['finance'])
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Finance</span>
                <a href="{{ route('admin.finance.index') }}" class="btn btn-outline-secondary btn-sm">Open</a>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-muted">Platform revenue</span><span class="fw-semibold">{{ $money($overview['finance']['revenue']) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Vendor payable</span><span>{{ $money($overview['finance']['payable']) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Pending payouts</span><span>{{ $overview['finance']['pending_payout_count'] }} &middot; {{ $money($overview['finance']['pending_payout_amount']) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Open refunds</span><span>{{ $overview['finance']['open_refund_count'] }} ({{ $money($overview['finance']['open_refund_amount']) }})</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Adjustments</span><span>{{ $money($overview['finance']['adjustments']) }}</span></div>
            </div>
        </div>
    </div>
    @endif

    @if ($panels['support'])
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Support</span>
                <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-outline-secondary btn-sm">Open</a>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-muted">Open tickets</span><span class="fw-semibold">{{ $overview['support']['open'] }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Unassigned</span><span>{{ $overview['support']['unassigned'] }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">SLA breached</span><span class="text-danger">{{ $overview['support']['breached'] }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Escalated</span><span>{{ $overview['support']['escalated'] }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Avg first response</span><span>{{ $overview['support']['avg_first_response_minutes'] === null ? 'n/a' : $overview['support']['avg_first_response_minutes'].' min' }}</span></div>
            </div>
        </div>
    </div>
    @endif

</div>

@if ($panels['intelligence'])
<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Intelligence</span>
                <a href="{{ route('admin.intelligence.index') }}" class="btn btn-outline-secondary btn-sm">Open</a>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-muted">Algorithms active</span><a href="{{ route('admin.algorithms.index') }}" class="fw-semibold">{{ $intelligence['algorithms_active'] }} / {{ $intelligence['algorithms_total'] }}</a></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Automation runs retrying</span><a href="{{ route('admin.automation.runs') }}">{{ $intelligence['automation_retrying'] }}</a></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Automation dead-letter</span><a href="{{ route('admin.automation.runs') }}" class="text-danger">{{ $intelligence['automation_dead_letter'] }}</a></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Open anomalies</span><a href="{{ route('admin.intelligence.anomalies') }}" class="fw-semibold">{{ $intelligence['open_anomalies'] }} <span class="text-danger">({{ $intelligence['critical_anomalies'] }})</span></a></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Discovery impressions</span><span>{{ number_format($intelligence['impressions']) }} &middot; CTR {{ number_format($intelligence['ctr'] * 100, 2) }}%</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Association pairs</span><span>{{ number_format($intelligence['association_pairs']) }}</span></div>
                @isset($intelligence['search_index_documents'])
                <div class="d-flex justify-content-between"><span class="text-muted">Search index</span><a href="{{ route('admin.search.index') }}" class="fw-semibold">{{ number_format($intelligence['search_index_documents']) }} docs <span class="text-warning">({{ number_format($intelligence['search_index_stale']) }} stale)</span></a></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Search anomalies</span><span>{{ number_format($intelligence['search_zero_result_anomalies']) }}</span></div>
                @endisset
            </div>
        </div>
    </div>
</div>
@endif

@if ($panels['governance'])
<div class="row g-3 mt-1">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Governance</div>
            <div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-muted">Pending approvals</span><a href="{{ route('admin.approvals.index') }}" class="fw-semibold">{{ $overview['governance']['pending_approvals'] }}</a></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Audit events (24h)</span><span>{{ $overview['governance']['audit_events_24h'] }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Features enabled</span><span>{{ $overview['governance']['features_enabled'] }} / {{ $overview['governance']['features_total'] }}</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Recent activity</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse ($activity as $row)
                            <tr>
                                <td class="text-muted small">{{ optional($row['created_at'])->diffForHumans() }}</td>
                                <td class="small">{{ $row['action'] }}</td>
                                <td class="small text-muted">{{ $row['subject'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted small p-3">No recent activity.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

@if (collect($panels)->filter()->isEmpty())
    <div class="alert alert-info mt-3">You do not have access to any Command Center module yet.</div>
@endif

@endsection
