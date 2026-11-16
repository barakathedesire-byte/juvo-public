@extends('admin.layouts.admin')

@section('title', 'HR — Employees')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Employees</h4>
        <a href="{{ route('admin.hr.employees.create') }}" class="btn btn-primary btn-sm">Add employee</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Emp #</th><th>Name</th><th>Dept</th><th>Position</th><th>Status</th><th>Manager</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($employees as $e)
                        <tr>
                            <td>{{ $e->employee_number }}</td>
                            <td>{{ $e->user->name ?? '—' }}</td>
                            <td>{{ $e->department->name ?? '—' }}</td>
                            <td>{{ $e->position->name ?? '—' }}</td>
                            <td>{{ $e->status }}</td>
                            <td>{{ $e->manager?->user?->name ?? '—' }}</td>
                            <td class="text-end"><a href="{{ route('admin.hr.employees.edit', $e->id) }}" class="btn btn-sm btn-outline-primary">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $employees->links() }}</div>
    </div>
@endsection