@extends('agent.layouts.agent')

@section('content')
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h6 class="mb-2">Your referral link</h6>
        <div class="input-group">
            <input id="refLink" class="form-control" value="{{ $link }}" readonly>
            <button class="btn btn-outline-dark" type="button" onclick="navigator.clipboard.writeText(document.getElementById('refLink').value)">Copy</button>
        </div>
        <div class="text-muted small mt-2">Referral code: <strong>{{ $code }}</strong> — share this link; orders placed through it are attributed to you.</div>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach ([['Leads', $leadStats['total']], ['Open leads', $leadStats['open']], ['Won leads', $leadStats['won']]] as [$label, $value])
        <div class="col-4">
            <div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted" style="font-size:12px;">{{ $label }}</div><div class="fw-bold">{{ $value }}</div></div></div>
        </div>
    @endforeach
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h6 class="mb-3">Attributed orders</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Commission</th><th>Confirmed</th><th></th></tr></thead>
                <tbody>
                    @forelse ($attributions as $attribution)
                        <tr>
                            <td>#{{ $attribution->order_id }}</td>
                            <td>{{ number_format((float) $attribution->order_total, 2) }}</td>
                            <td>{{ $attribution->status }}</td>
                            <td>{{ number_format((float) $attribution->commission_amount, 2) }}</td>
                            <td>{!! $attribution->agent_confirmed ? '<span class="badge bg-success">yes</span>' : '<span class="badge bg-secondary">no</span>' !!}</td>
                            <td>
                                @if (! $attribution->agent_confirmed)
                                    <form method="POST" action="{{ route('agent.referrals.confirm', $attribution->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success">Confirm</button></form>
                                @endif
                                <form method="POST" action="{{ route('agent.referrals.dispute', $attribution->id) }}" class="d-inline">@csrf<input type="hidden" name="reason" value="Disputed by agent"><button class="btn btn-sm btn-outline-danger">Dispute</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center">No attributed orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $attributions->links() }}
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="mb-3">Leads</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Name</th><th>Phone</th><th>Source</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse ($leads as $lead)
                        <tr>
                            <td>{{ $lead->name }}</td>
                            <td>{{ $lead->phone ?? '—' }}</td>
                            <td>{{ $lead->source }}</td>
                            <td>{{ $lead->status }}</td>
                            <td>{{ $lead->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center">No leads yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $leads->links() }}
    </div>
</div>
@endsection
