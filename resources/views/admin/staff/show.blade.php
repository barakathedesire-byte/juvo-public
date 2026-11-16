@extends('admin.layouts.admin')

@section('title', 'Staff profile')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">{{ $staff->name }}</h4>
        <a href="{{ route('admin.staff.edit', $staff->id) }}" class="btn btn-primary btn-sm">Edit</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Account</h6>
                    <p class="mb-1"><strong>Email:</strong> {{ $staff->email }}</p>
                    <p class="mb-1"><strong>Role:</strong>
                        @foreach($staff->roles as $role)<span class="badge text-bg-primary">{{ $role->name }}</span>@endforeach
                    </p>
                    <p class="mb-1"><strong>Department:</strong> {{ $staff->department->name ?? '—' }}</p>
                    <p class="mb-1"><strong>Position:</strong> {{ $staff->position ?? '—' }}</p>
                    <p class="mb-1"><strong>Status:</strong> {{ $staff->account_status ?? 'active' }}</p>
                    <p class="mb-1"><strong>Last login:</strong> {{ $staff->last_login_at?->toDateTimeString() ?? '—' }}</p>
                    <p class="mb-0"><strong>Created:</strong> {{ $staff->created_at?->toDateString() }}</p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body d-grid gap-2">
                    <form method="POST" action="{{ route('admin.staff.revokeSessions', $staff->id) }}">
                        @csrf
                        <button class="btn btn-outline-warning w-100">Revoke active sessions</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Granted permissions ({{ $permissions->count() }})</h6></div>
                <div class="card-body">
                    @forelse($permissions->chunk(6) as $chunk)
                        <div class="row">
                            @foreach($chunk as $permission)
                                <div class="col-md-4"><code class="small">{{ $permission }}</code></div>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-muted mb-0">No permissions granted.</p>
                    @endforelse
                    @if($staff->hasRole('super_admin'))
                        <p class="text-muted small mt-2 mb-0">Super admins bypass individual permissions.</p>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h6 class="mb-0">Recent approval requests</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Reference</th><th>Ability</th><th>Status</th><th>When</th></tr></thead>
                        <tbody>
                            @forelse($approvals as $approval)
                                <tr>
                                    <td><a href="{{ route('admin.approvals.show', $approval->id) }}">{{ $approval->reference }}</a></td>
                                    <td>{{ $approval->ability }}</td>
                                    <td>{{ $approval->status }}</td>
                                    <td>{{ $approval->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">No requests.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
