@extends('admin.layouts.admin')

@section('title', 'HR — Attendance')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Attendance — {{ $date }}</h4>
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-outline-secondary">Show</button>
        </form>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="alert alert-info py-2">
        Attendance is recorded manually by HR staff. No biometric or GPS source is configured.
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Employee</th><th>Status</th><th>Recorded by</th><th class="text-end">Set</th></tr></thead>
                <tbody>
                    @foreach($employees as $e)
                        @php($rec = $records->get($e->id))
                        <tr>
                            <td>{{ $e->employee_number }} — {{ $e->user->name ?? '' }}</td>
                            <td>{{ $rec->status ?? '—' }}</td>
                            <td>{{ $rec?->recorder?->name ?? '—' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.hr.attendance.store') }}" class="d-flex gap-1 justify-content-end">
                                    @csrf
                                    <input type="hidden" name="employee_id" value="{{ $e->id }}">
                                    <input type="hidden" name="attendance_date" value="{{ $date }}">
                                    <select name="status" class="form-select form-select-sm" style="width:auto">
                                        @foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection