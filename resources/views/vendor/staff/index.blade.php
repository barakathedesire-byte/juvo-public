@extends('vendor.layouts.master')

@section('title', 'Staff')

@section('content')
    <h4 class="mb-3"><i class="fas fa-users-cog me-2" style="color:#f68b1e;"></i>Staff accounts</h4>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg"><h6 class="mb-0 text-white">Invite a team member</h6></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vendor.staff.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required maxlength="120" value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="staff">Staff</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm password</label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                        </div>
                        <button class="btn btn-success w-100">Create account</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg"><h6 class="mb-0 text-white">Team</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($staff as $member)
                                    <tr>
                                        <td>{{ $member->name }}</td>
                                        <td>{{ $member->email }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('vendor.staff.update', $member->id) }}" class="d-flex gap-1">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="name" value="{{ $member->name }}">
                                                <select name="role" class="form-select form-select-sm" style="width:auto;">
                                                    @foreach (['owner', 'manager', 'staff'] as $role)
                                                        <option value="{{ $role }}" {{ $member->role === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="status" class="form-select form-select-sm" style="width:auto;">
                                                    <option value="active" {{ $member->status === 'active' ? 'selected' : '' }}>Active</option>
                                                    <option value="inactive" {{ $member->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                                </select>
                                                <button class="btn btn-sm btn-outline-primary">Save</button>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-{{ $member->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($member->status) }}</span>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('vendor.staff.destroy', $member->id) }}" onsubmit="return confirm('Remove this staff member?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" {{ $member->role === 'owner' ? 'disabled' : '' }}><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No staff accounts yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
