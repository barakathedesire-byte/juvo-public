@extends('logistics.layouts.app')

@section('title', 'Fraud & Risk Intelligence')
@section('heading', 'Fraud & risk intelligence')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Fraud & Risk</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Security</span><h2>Risk intelligence center</h2><p>Detection supports investigation; anomalies are not proof of fraud.</p></div>
    <div class="page-actions"><form method="POST" action="{{ route('logistics.fraud.scan') }}">@csrf<button class="btn btn-juvo btn-sm">Run scan</button></form></div>
</div>

<div class="row g-3 mb-4">
    @foreach([['Total cases', $kpis['total'], ''], ['Open investigations', $kpis['open'], 'text-warning'], ['Critical', $kpis['critical'], 'text-danger'], ['COD anomalies', $kpis['cod'], ''], ['Payout anomalies', $kpis['payout'], ''], ['Resolved', $kpis['resolved'], 'text-success']] as $card)
        <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">{{ $card[0] }}</div><div class="stat-value {{ $card[2] }}">{{ $card[1] }}</div></div></div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Search</label><input name="q" value="{{ request('q') }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['new','under_review','investigation_in_progress','awaiting_evidence','escalated','resolved','closed','false_positive'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Severity</label><select name="severity" class="form-select"><option value="">All</option>@foreach(['low','moderate','high','critical'] as $severity)<option value="{{ $severity }}" @selected(request('severity')===$severity)>{{ $severity }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Type</label><input name="anomaly_type" value="{{ request('anomaly_type') }}" class="form-control"></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-juvo w-100">Filter</button><a class="btn btn-outline-secondary" href="{{ route('logistics.fraud.index') }}">Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Case</th><th>Type</th><th>Severity</th><th>Subject</th><th>Risk score</th><th>Status</th><th>Assigned</th><th>Created</th><th></th></tr></thead>
            <tbody>
                @forelse($cases as $case)
                    <tr>
                        <td>{{ $case->case_number }}</td>
                        <td>{{ $case->anomaly_type }}</td>
                        <td>{{ $case->severity }}</td>
                        <td>{{ $case->subject_type ? class_basename($case->subject_type).' #'.$case->subject_id : '—' }}</td>
                        <td>{{ $case->risk_score }}</td>
                        <td>{{ $case->status }}</td>
                        <td>{{ $case->assignedTo?->name ?? 'Unassigned' }}</td>
                        <td>{{ optional($case->created_at)->format('d M Y H:i') }}</td>
                        <td class="text-end"><a href="{{ route('logistics.fraud.show', $case) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">No risk cases detected.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $cases->links() }}</div>
</div>
@endsection
