@extends('admin.layouts.admin')

@section('title', 'HR — Departments')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Departments</h4>
        <a href="{{ route('admin.hr.departments.create') }}" class="btn btn-primary btn-sm">Add department</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Name</th><th>Staff</th><th>Positions</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach($departments as $d)
                        <tr>
                            <td>{{ $d->name }}</td>
                            <td>{{ $d->users_count }}</td>
                            <td>{{ $d->positions_count }}</td>
                            <td>{{ $d->is_active ? 'active' : 'inactive' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection