@extends('admin.layouts.admin')

@section('title', 'Add staff')

@section('content')
    <div class="mt-4 mb-3">
        <h4 class="mb-0">Invite staff account</h4>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.staff.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">— none —</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ (string) old('department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Position</label>
                    <input name="position" class="form-control" value="{{ old('position') }}" placeholder="e.g. Senior Agent">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Create account</button>
                    <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
