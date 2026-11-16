@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
        <h6 class="mb-0">Features</h6>
    </div>

    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        @can('features.create')
        <form action="{{ route('admin.features.store') }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Key</label>
                <input name="key" class="form-control form-control-sm" required maxlength="100" placeholder="sales_attribution">
            </div>
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <input name="name" class="form-control form-control-sm" required maxlength="150">
            </div>
            <div class="col-md-2">
                <label class="form-label">Module</label>
                <input name="module" class="form-control form-control-sm" maxlength="100">
            </div>
            <div class="col-md-2">
                <label class="form-label">Rollout %</label>
                <input type="number" name="rollout_percentage" class="form-control form-control-sm" min="0" max="100" value="100">
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary w-100">Register feature</button>
            </div>
        </form>
        @endcan

        <div class="table-responsive">
            <table class="table table-bordered dt-style">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Name</th>
                        <th>Module</th>
                        <th>Rollout</th>
                        <th>Status</th>
                        <th>Toggle</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($features as $feature)
                        <tr>
                            <td><code>{{ $feature->key }}</code></td>
                            <td>{{ $feature->name }}</td>
                            <td>{{ $feature->module ?: '—' }}</td>
                            <td>{{ $feature->rollout_percentage }}%</td>
                            <td>
                                @if ($feature->is_enabled)
                                    <span class="badge bg-success">Enabled</span>
                                @else
                                    <span class="badge bg-secondary">Disabled</span>
                                @endif
                            </td>
                            <td>
                                @can('features.toggle')
                                <form action="{{ route('admin.features.toggle', $feature->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="is_enabled" value="{{ $feature->is_enabled ? 0 : 1 }}">
                                    <button class="btn btn-sm btn-outline-{{ $feature->is_enabled ? 'danger' : 'success' }}">
                                        {{ $feature->is_enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                @endcan
                            </td>
                            <td class="d-flex gap-1">
                                @can('features.edit')
                                <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#feature-edit-{{ $feature->id }}">Edit</button>
                                @endcan
                                @can('features.delete')
                                <form action="{{ route('admin.features.destroy', $feature->id) }}" method="POST" onsubmit="return confirm('Delete this feature?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @can('features.edit')
                        <tr class="collapse" id="feature-edit-{{ $feature->id }}">
                            <td colspan="7">
                                <form action="{{ route('admin.features.update', $feature->id) }}" method="POST" class="row g-2 align-items-end">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-2"><input name="key" value="{{ $feature->key }}" class="form-control form-control-sm" required></div>
                                    <div class="col-md-3"><input name="name" value="{{ $feature->name }}" class="form-control form-control-sm" required></div>
                                    <div class="col-md-2"><input name="module" value="{{ $feature->module }}" class="form-control form-control-sm"></div>
                                    <div class="col-md-2"><input type="number" name="rollout_percentage" value="{{ $feature->rollout_percentage }}" min="0" max="100" class="form-control form-control-sm"></div>
                                    <div class="col-md-3"><button class="btn btn-sm btn-dark w-100">Save changes</button></div>
                                    <div class="col-12"><textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Description">{{ $feature->description }}</textarea></div>
                                </form>
                            </td>
                        </tr>
                        @endcan
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No features registered.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
