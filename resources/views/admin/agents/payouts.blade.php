@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Agent Payout Requests</h6>
        <a href="{{ route('admin.sales-agents.index') }}" class="btn btn-sm btn-light">Back</a>
    </div>
    <div class="card-body">
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <form action="{{ route('admin.sales-agents.payouts.store') }}" method="POST" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-5">
                <label class="form-label">Agent</label>
                <select name="agent_id" class="form-select" required>
                    <option value="">Select an agent…</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Amount (blank = full balance)</label>
                <input type="number" step="0.01" min="0" name="amount" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Create payout</button>
            </div>
        </form>

        <table id="payouts-table" class="table table-bordered dt-style">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reference</th>
                    <th>Agent</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Paid</th>
                    <th></th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
@php $datatableLang = __('cms.datatables'); @endphp
<script>
$(document).ready(function() {
    $('#payouts-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.sales-agents.payouts.getData') }}",
        language: @json($datatableLang),
        columns: [
            { data: 'id', name: 'id' },
            { data: 'reference', name: 'reference' },
            { data: 'agent_name', name: 'agent_id', orderable: false, searchable: false },
            { data: 'amount', name: 'amount' },
            { data: 'method', name: 'method' },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'requested_at', name: 'requested_at' },
            { data: 'paid_at', name: 'paid_at' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15
    });
});
</script>
@endsection
