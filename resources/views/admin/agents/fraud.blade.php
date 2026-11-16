@extends('admin.layouts.admin')

@section('content')
<div class="row g-3 mt-4">
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Open cases</div><div class="fw-bold fs-4">{{ $summary['open'] }}</div></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">High / critical</div><div class="fw-bold fs-4">{{ $summary['high'] }}</div></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Resolved</div><div class="fw-bold fs-4">{{ $summary['resolved'] }}</div></div></div></div>

    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Agent Fraud Reviews</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.sales-agents.index') }}" class="btn btn-sm btn-light">Back</a>
                    <form method="POST" action="{{ route('admin.sales-agents.fraud.scan') }}">@csrf<button class="btn btn-sm btn-light">Run scan now</button></form>
                </div>
            </div>
            <div class="card-body">
                <table id="fraud-table" class="table table-bordered dt-style">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Agent</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Opened</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
@php $datatableLang = __('cms.datatables'); @endphp
<script>
$(document).ready(function() {
    $('#fraud-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.sales-agents.fraud.getData') }}",
        language: @json($datatableLang),
        columns: [
            { data: 'id', name: 'id' },
            { data: 'agent_name', name: 'agent_id', orderable: false, searchable: false },
            { data: 'type', name: 'type' },
            { data: 'severity_badge', name: 'severity', orderable: false, searchable: false },
            { data: 'score', name: 'score' },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15
    });
});
</script>
@endsection
