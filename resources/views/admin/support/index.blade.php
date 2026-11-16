@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="d-flex align-items-center mb-0 dt-heading">Support Tickets</h6>
        <div class="d-flex gap-2">
            @can('support.manage_canned')
                <a href="{{ route('admin.support.canned.index') }}" class="btn btn-sm btn-light">Canned responses</a>
            @endcan
            @can('support.manage_sla')
                <a href="{{ route('admin.support.sla.index') }}" class="btn btn-sm btn-light">SLA rules</a>
            @endcan
        </div>
    </div>

    <div class="card-body">
        <form id="ticket-filters" class="row g-2 mb-3">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All priorities</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="assigned_to" class="form-select form-select-sm">
                    <option value="">Any assignee</option>
                    <option value="unassigned" @selected(($filters['assigned_to'] ?? '') === 'unassigned')>Unassigned</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $agent->id)>{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>

        <table id="support-table" class="table table-bordered mt-2 dt-style">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ticket</th>
                    <th>Requester</th>
                    <th>Contact</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>SLA</th>
                    <th>Assignee</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
@php $datatableLang = __('cms.datatables'); @endphp

@if (session('success'))
<script>
    toastr.success("{{ session('success') }}", "Success", { timeOut: 5000 });
</script>
@endif

<script>
$(document).ready(function() {
    var table = $('#support-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.support-tickets.getData') }}",
            data: function (d) {
                $('#ticket-filters').serializeArray().forEach(function (field) {
                    d[field.name] = field.value;
                });
            }
        },
        language: @json($datatableLang),
        columns: [
            { data: 'id', name: 'id' },
            { data: 'ticket_number', name: 'ticket_number' },
            { data: 'requester', name: 'requester', orderable: false, searchable: false },
            { data: 'contact', name: 'contact', orderable: false, searchable: false },
            { data: 'subject', name: 'subject' },
            { data: 'category', name: 'category' },
            { data: 'priority', name: 'priority' },
            { data: 'status', name: 'status' },
            { data: 'sla', name: 'sla', orderable: false, searchable: false },
            { data: 'assignee', name: 'assignee', orderable: false, searchable: false },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 10
    });

    $('#ticket-filters').on('submit', function (e) {
        e.preventDefault();
        table.ajax.reload();
    });
});
</script>
@endsection
