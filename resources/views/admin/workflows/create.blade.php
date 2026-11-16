@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Add workflow</h4>
        <a href="{{ route('admin.workflows.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.workflows.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <input type="text" name="type" class="form-control" value="{{ old('type', 'general') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Trigger</label>
                        <input type="text" name="trigger" class="form-control" value="{{ old('trigger') }}" placeholder="e.g. order_status_change">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Config (JSON)</label>
                        <textarea name="config" class="form-control" rows="3" placeholder='{"threshold_rule":"low_stock_threshold"}'>{{ old('config') }}</textarea>
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <button class="btn btn-primary mt-3">Save workflow</button>
            </form>
        </div>
    </div>
@endsection