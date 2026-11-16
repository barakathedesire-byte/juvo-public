@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Edit workflow</h4>
        <a href="{{ route('admin.workflows.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.workflows.update', $workflow->id) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $workflow->name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <input type="text" name="type" class="form-control" value="{{ old('type', $workflow->type) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Trigger</label>
                        <input type="text" name="trigger" class="form-control" value="{{ old('trigger', $workflow->trigger) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $workflow->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Config (JSON)</label>
                        @php
                            $configText = is_array($workflow->config) ? json_encode($workflow->config, JSON_PRETTY_PRINT) : $workflow->config;
                        @endphp
                        <textarea name="config" class="form-control" rows="4">{{ old('config', $configText) }}</textarea>
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" {{ $workflow->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <button class="btn btn-primary mt-3">Update workflow</button>
            </form>
        </div>
    </div>
@endsection