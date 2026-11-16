@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-list-check me-2" style="color:#f68b1e;"></i>Automation runs</h4>
        <a href="{{ route('admin.automation.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Workflows</a>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary">Filter</button></div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Run</th><th>Workflow</th><th>Trigger</th><th>Status</th><th>Attempts</th><th>Started</th><th>Error</th></tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td><code class="small">{{ \Illuminate\Support\Str::limit($run->run_uuid, 8, '') }}</code></td>
                            <td>{{ $run->workflow?->name ?? '—' }}</td>
                            <td><span class="badge text-bg-light border">{{ $run->trigger ?? '—' }}</span></td>
                            <td><span class="badge {{ $run->status === 'succeeded' ? 'text-bg-success' : ($run->status === 'dead_letter' ? 'text-bg-danger' : 'text-bg-light border') }}">{{ $run->status }}</span></td>
                            <td>{{ $run->attempt }}/{{ $run->max_attempts }}</td>
                            <td class="small text-muted">{{ optional($run->started_at)->diffForHumans() ?? '—' }}</td>
                            <td class="small text-danger">{{ \Illuminate\Support\Str::limit($run->error, 60) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No runs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $runs->withQueryString()->links() }}</div>
@endsection
