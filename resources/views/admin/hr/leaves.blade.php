@extends('admin.layouts.admin')

@section('title', 'HR — Leave')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Leave Requests</h4>
        <div>
            @foreach(['pending','approved','rejected'] as $s)
                <a href="{{ route('admin.hr.leaves.index', ['status' => $s]) }}" class="btn btn-sm btn-outline-secondary">{{ ucfirst($s) }}</a>
            @endforeach
            <a href="{{ route('admin.hr.leaves.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.hr.leaves.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Employee</label>
                    <select name="employee_id" class="form-select" required>
                        @foreach($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->employee_number }} — {{ $e->user->name ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Leave type</label>
                    <select name="leave_type_id" class="form-select" required>
                        @foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">From</label><input type="date" name="start_date" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label">To</label><input type="date" name="end_date" class="form-control" required></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Request</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @foreach($leaves as $l)
                        <tr>
                            <td>{{ $l->employee?->user?->name ?? $l->employee?->employee_number }}</td>
                            <td>{{ $l->leaveType?->name }}</td>
                            <td>{{ $l->start_date?->format('Y-m-d') }}</td>
                            <td>{{ $l->end_date?->format('Y-m-d') }}</td>
                            <td>{{ $l->days }}</td>
                            <td>{{ $l->status }}</td>
                            <td class="text-end">
                                @if($l->isPending())
                                    <form method="POST" action="{{ route('admin.hr.leaves.approve', $l->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success">Approve</button></form>
                                    <form method="POST" action="{{ route('admin.hr.leaves.reject', $l->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger">Reject</button></form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $leaves->links() }}</div>
    </div>
@endsection