@extends('admin.layouts.admin')

@section('title', 'Staff')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Staff &amp; Access Control</h4>
        <div>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield-lock me-1"></i> Roles &amp; permissions</a>
            @if(\App\Support\Access\AdminTabRegistry::canWrite('staff'))
            <a href="{{ route('admin.staff.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Invite staff</a>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th>
                        <th>Status</th><th>Last login</th><th>Pending</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>
                                <a href="{{ route('admin.staff.show', $user->id) }}">{{ $user->name }}</a>
                                @if($user->id === auth()->id())<span class="badge text-bg-secondary">you</span>@endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @forelse($user->roles as $role)
                                    <span class="badge text-bg-primary">{{ $role->name }}</span>
                                @empty
                                    <span class="badge text-bg-light text-muted">{{ $user->role ?: 'none' }}</span>
                                @endforelse
                            </td>
                            <td>{{ $user->department->name ?? '—' }}</td>
                            <td>
                                @if(($user->account_status ?? 'active') === 'active')
                                    <span class="badge text-bg-success">active</span>
                                @else
                                    <span class="badge text-bg-warning">suspended</span>
                                @endif
                            </td>
                            <td>{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                @if($user->pending_approvals_count > 0)
                                    <span class="badge text-bg-info">{{ $user->pending_approvals_count }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                @if(\App\Support\Access\AdminTabRegistry::canWrite('staff'))
                                <a href="{{ route('admin.staff.edit', $user->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.staff.toggleStatus', $user->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning">{{ ($user->account_status ?? 'active') === 'active' ? 'Suspend' : 'Activate' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.staff.destroy', $user->id) }}" class="d-inline" onsubmit="return confirm('Delete this staff account?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
