@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Sales Leads</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sales.reps.index') }}" class="btn btn-sm btn-light">Salespeople</a>
            @can('sales_attribution.view')
                <a href="{{ route('admin.sales.attribution.index') }}" class="btn btn-sm btn-light">Attribution</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <form id="lead-filters" class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="sales_rep_id" class="form-select form-select-sm">
                    <option value="">All salespeople</option>
                    @foreach ($reps as $rep)
                        <option value="{{ $rep->id }}" @selected((string) ($filters['sales_rep_id'] ?? '') === (string) $rep->id)>{{ $rep->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        </form>

        <table id="leads-table" class="table table-bordered dt-style">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Salesperson</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Update</th>
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
    var table = $('#leads-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.sales.leads.getData') }}",
            data: function (d) {
                $('#lead-filters').serializeArray().forEach(function (field) { d[field.name] = field.value; });
            }
        },
        language: @json($datatableLang),
        columns: [
            { data: 'id', name: 'id' },
            { data: 'rep', name: 'rep', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'email', name: 'email' },
            { data: 'source', name: 'source' },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 10
    });

    $('#lead-filters').on('submit', function (e) { e.preventDefault(); table.ajax.reload(); });
});
</script>
@endsection
