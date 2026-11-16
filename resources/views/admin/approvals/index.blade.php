@extends('admin.layouts.admin')

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h4 class="mb-0">Approvals</h4>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<ul class="nav nav-pills flex-wrap gap-2 mb-3">
    @php
        $tabs = [
            'pending' => 'Pending',
            'assigned' => 'Assigned to me',
            'my_requests' => 'My requests',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'returned' => 'Returned for changes',
            'escalated' => 'Escalated',
            'all' => 'All',
        ];
    @endphp
    @foreach($tabs as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $status === $key ? 'active' : '' }}"
               href="{{ route('admin.approvals.index', ['status' => $key]) }}">
                {{ $label }}
                @if(isset($counts[$key]))<span class="badge text-bg-light ms-1">{{ $counts[$key] }}</span>@endif
            </a>
        </li>
    @endforeach
</ul>

<div class="card">
    <div class="card-body">
        <table id="approvals-table" class="table table-bordered dt-style">
            <thead>
                <tr>
                    <th>Reference</th><th>Operation</th><th>Requested by</th><th>Amount</th>
                    <th>Risk</th><th>Status</th><th>Created</th><th>Action</th>
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
    $('#approvals-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.approvals.data', ['status' => $status]) }}",
        columns: [
            { data: 'reference', name: 'reference' },
            { data: 'ability_label', name: 'ability', orderable: false },
            { data: 'requester_name', name: 'requester_name', orderable: false },
            { data: 'amount_display', name: 'amount', orderable: false },
            { data: 'risk', name: 'risk' },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        pageLength: 15
    });
});
</script>
@endsection
