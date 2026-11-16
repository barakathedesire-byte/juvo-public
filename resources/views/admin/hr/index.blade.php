@extends('admin.layouts.admin')

@section('title', 'HR Department')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">HR Department — Overview</h4>
        <div>
            <a href="{{ route('admin.hr.departments') }}" class="btn btn-outline-secondary btn-sm">Departments</a>
            <a href="{{ route('admin.hr.positions') }}" class="btn btn-outline-secondary btn-sm">Positions</a>
            <a href="{{ route('admin.hr.employees') }}" class="btn btn-outline-secondary btn-sm">Employees</a>
            <a href="{{ route('admin.hr.documents') }}" class="btn btn-outline-secondary btn-sm">Documents</a>
            <a href="{{ route('admin.hr.leaves.index') }}" class="btn btn-outline-secondary btn-sm">Leave</a>
            <a href="{{ route('admin.hr.attendance.index') }}" class="btn btn-outline-secondary btn-sm">Attendance</a>
            <a href="{{ route('admin.hr.recruitment.index') }}" class="btn btn-outline-secondary btn-sm">Recruitment</a>
            <a href="{{ route('admin.hr.reports.index') }}" class="btn btn-outline-secondary btn-sm">Reports</a>
            @can('hr.view_compensation')
                <a href="{{ route('admin.hr.compensation.index') }}" class="btn btn-outline-secondary btn-sm">Compensation</a>
            @endcan
            @can('hr.view_bank_details')
                <a href="{{ route('admin.hr.bank.index') }}" class="btn btn-outline-secondary btn-sm">Bank details</a>
            @endcan
        </div>
    </div>
    <div class="row g-3 mb-4">
        @foreach(['departments','positions','employees_total','employees_active'] as $k)
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">{{ ucwords(str_replace('_',' ',$k)) }}</h6>
                        <h3>{{ $stats[$k] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection