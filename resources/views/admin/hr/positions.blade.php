@extends('admin.layouts.admin')

@section('title', 'HR — Positions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Positions</h4>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.hr.positions.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">—</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Department</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($positions as $p)
                        <tr><td>{{ $p->name }}</td><td>{{ $p->department->name ?? '—' }}</td><td>{{ $p->is_active ? 'active' : 'inactive' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection