@extends('admin.layouts.admin')

@section('title', 'Logistics Staff')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Logistics Staff</h4>
        <a href="{{ route('admin.logistics-staff.create') }}" class="btn btn-primary btn-sm">Add staff</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($staff as $member)
                        <tr>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>{{ \App\Models\Logistics\LogisticsAdmin::ROLES[$member->role] ?? $member->role }}</td>
                            <td>
                                <span class="badge {{ $member->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $member->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.logistics-staff.edit', $member) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.logistics-staff.destroy', $member) }}" class="d-inline" onsubmit="return confirm('Delete this account?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No logistics staff yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
