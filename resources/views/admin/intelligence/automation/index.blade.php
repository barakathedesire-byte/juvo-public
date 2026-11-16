@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h4 class="mb-0"><i class="fas fa-robot me-2" style="color:#f68b1e;"></i>Automation Engine</h4>
            <p class="text-muted mb-0 small">Registered workflows with retries, timeouts and a dead-letter queue.</p>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Total runs</div>
            <div class="fs-4 fw-semibold">{{ number_format($stats['total']) }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Retrying</div>
            <div class="fs-4 fw-semibold">{{ number_format($stats['retrying']) }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Dead letter</div>
            <div class="fs-4 fw-semibold text-danger">{{ number_format($stats['dead_letter']) }}</div>
        </div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span>Workflows</span>
            <a href="{{ route('admin.automation.runs') }}" class="small">View all runs &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Workflow</th><th>Action</th><th>Trigger</th><th>Runs</th><th>Status</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                    @forelse($workflows as $workflow)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $workflow->name }}</span>
                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($workflow->description, 90) }}</div>
                            </td>
                            <td><code>{{ $workflow->actionKey() }}</code></td>
                            <td><span class="badge text-bg-light border">{{ $workflow->trigger_type ?? 'manual' }}</span></td>
                            <td>{{ $workflow->runs_count }}</td>
                            <td><span class="badge {{ $workflow->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $workflow->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.automation.show', $workflow) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                <form method="POST" action="{{ route('admin.automation.run', $workflow) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">Run</button>
                                </form>
                                <form method="POST" action="{{ route('admin.automation.toggle', $workflow) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ $workflow->is_active ? 'Disable' : 'Enable' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No workflows registered.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Registered actions</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                @foreach($actions as $action)
                    <span class="badge text-bg-light border">
                        <code>{{ $action['key'] }}</code> — {{ $action['label'] }}
                        @if($action['mutates'])<span class="text-warning" title="Mutates state">&bull;</span>@endif
                    </span>
                @endforeach
            </div>
        </div>
    </div>
@endsection
