@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h4 class="mb-0">{{ $workflow->name }}</h4>
            <p class="text-muted mb-0 small"><code>{{ $workflow->actionKey() }}</code> · {{ $workflow->trigger_type ?? 'manual' }}</p>
        </div>
        <a href="{{ route('admin.automation.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Automation</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Definition</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Status</dt><dd class="col-7">{{ $workflow->is_active ? 'Active' : 'Inactive' }}</dd>
                        <dt class="col-5">Max attempts</dt><dd class="col-md-7">{{ $workflow->max_attempts }}</dd>
                        <dt class="col-5">Timeout</dt><dd class="col-7">{{ $workflow->timeout_seconds }}s</dd>
                        <dt class="col-5">Retry policy</dt><dd class="col-7"><code>{{ json_encode($workflow->retry_policy ?? []) }}</code></dd>
                    </dl>
                    <hr>
                    <div class="mb-1 fw-semibold small text-uppercase">Steps</span></div>
                    <ol class="small mb-0">
                        @foreach($workflow->steps() as $step)
                            <li><code>{{ $step['action'] ?? '—' }}</code></li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Recent runs</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Run</th><th>Status</th><th>Attempts</th><th></th></tr></thead>
                        <tbody>
                            @forelse($runs as $run)
                                <tr>
                                    <td><code class="small">{{ \Illuminate\Support\Str::limit($run->run_uuid, 8, '') }}</code></td>
                                    <td><span class="badge {{ $run->status === 'succeeded' ? 'text-bg-success' : ($run->status === 'dead_letter' ? 'text-bg-danger' : 'text-bg-light border') }}">{{ $run->status }}</span></td>
                                    <td>{{ $run->attempt }}/{{ $run->max_attempts }}</td>
                                    <td class="text-end">
                                        @if(in_array($run->status, ['failed', 'dead_letter']))
                                            <form method="POST" action="{{ route('admin.automation.retry', $run) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary">Retry</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted small">No runs yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
