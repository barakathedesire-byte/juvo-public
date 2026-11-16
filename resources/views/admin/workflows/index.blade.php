@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Workflows</h4>
        @if(\App\Support\Access\AdminTabRegistry::canWrite('workflows'))
        <a href="{{ route('admin.workflows.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Add workflow</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header card-header-bg text-white">
            <h6 class="mb-0">Automated workflows across the platform</h6>
        </div>
        <div class="card-body">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Trigger</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workflows as $wf)
                        <tr>
                            <td>{{ $wf->id }}</td>
                            <td>
                                {{ $wf->name }}
                                @if($wf->description)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($wf->description, 80) }}</small>@endif
                            </td>
                            <td><span class="badge text-bg-light border">{{ $wf->type }}</span></td>
                            <td><code>{{ $wf->trigger ?? '—' }}</code></td>
                            <td>
                                <span class="badge {{ $wf->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $wf->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.workflows.edit', $wf->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.workflows.toggle', $wf->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ $wf->is_active ? 'Disable' : 'Enable' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.workflows.destroy', $wf->id) }}" class="d-inline"
                                      onsubmit="return confirm('Delete this workflow?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No workflows yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection