@extends('admin.layouts.admin')

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h4 class="mb-0">Audit log</h4>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <select id="audit-action" class="form-select">
                    <option value="">All actions</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <input id="audit-search" class="form-control" placeholder="Search action or reason">
            </div>
        </div>

        <table id="audit-table" class="table table-bordered dt-style">
            <thead>
                <tr>
                    <th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Reason</th><th>IP</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    var table = $('#audit-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.audit-logs.data') }}",
            data: function (d) {
                d.action = $('#audit-action').val();
                d.q = $('#audit-search').val();
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'actor_name', name: 'actor_name', orderable: false },
            { data: 'action', name: 'action' },
            { data: 'target', name: 'target', orderable: false },
            { data: 'reason', name: 'reason' },
            { data: 'ip', name: 'ip', orderable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 20
    });

    $('#audit-action').on('change', function () { table.ajax.reload(); });
    $('#audit-search').on('keyup', function () { table.ajax.reload(); });
});
</script>
@endsection
