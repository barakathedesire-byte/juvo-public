@extends('admin.layouts.admin')

@section('title', 'Staff audit')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Audit trail — {{ $staff->name }}</h4>
        <a href="{{ route('admin.staff.show', $staff->id) }}" class="btn btn-outline-secondary btn-sm">Back to profile</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Reason</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at?->toDateTimeString() }}</td>
                            <td>{{ $log->actor?->name ?? 'System' }}</td>
                            <td><code>{{ $log->action }}</code></td>
                            <td>{{ $log->reason ?? '—' }}</td>
                            <td>{{ $log->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No audit records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
