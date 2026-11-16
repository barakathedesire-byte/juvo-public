@extends('admin.layouts.admin')

@section('title', 'Edit staff')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Edit staff account</h4>
        <a href="{{ route('admin.staff.show', $user->id) }}" class="btn btn-outline-secondary btn-sm">View profile &amp; audit</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.staff.update', $user->id) }}" class="row g-3">
                @csrf @method('PUT')
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">New password <span class="text-muted small">(leave blank to keep)</span></label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ old('role', $user->roles->first()?->name) === $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">— none —</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ (string) old('department_id', $user->department_id) === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Position</label>
                    <input name="position" class="form-control" value="{{ old('position', $user->position) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Account status</label>
                    <select name="account_status" class="form-select">
                        <option value="active" {{ old('account_status', $user->account_status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ old('account_status', $user->account_status) === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Save</button>
                    <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
