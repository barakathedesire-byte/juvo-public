@extends('admin.layouts.admin')

@section('title', 'Roles & permissions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Roles &amp; Permissions</h4>
        @if(\App\Support\Access\AdminTabRegistry::canWrite('roles'))
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Create role</a>
        @endif
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Role</th><th>Permissions</th><th>Staff</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr>
                            <td>
                                <span class="badge text-bg-primary">{{ $role->name }}</span>
                                @if(in_array($role->name, ['super_admin', 'admin']))<span class="badge text-bg-dark">protected</span>@endif
                            </td>
                            <td>{{ $role->permissions_count }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td class="text-end">
                                @if(\App\Support\Access\AdminTabRegistry::canWrite('roles'))
                                <a href="{{ route('admin.roles.edit', $role->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.roles.duplicate', $role->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">Duplicate</button>
                                </form>
                                @unless(in_array($role->name, ['super_admin', 'admin']))
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role->id) }}" class="d-inline" onsubmit="return confirm('Delete this role?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endunless
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
