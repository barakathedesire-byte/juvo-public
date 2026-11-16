@extends('admin.layouts.admin')
@section('title', 'Product Report #' . $productReport->id)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Product Report #{{ $productReport->id }}</h4>
        <a href="{{ route('admin.product-reports.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Details</h5>
                    <dl class="row">
                        <dt class="col-sm-3">Product</dt>
                        <dd class="col-sm-9"><a href="{{ route('product.show', $productReport->product->slug) }}" target="_blank">{{ $productReport->product->slug }}</a></dd>
                        <dt class="col-sm-3">Issue Type</dt>
                        <dd class="col-sm-9">{{ ucfirst(str_replace('_', ' ', $productReport->issue_type)) }}</dd>
                        <dt class="col-sm-3">Description</dt>
                        <dd class="col-sm-9">{{ nl2br(e($productReport->description)) }}</dd>
                        <dt class="col-sm-3">Reporter</dt>
                        <dd class="col-sm-9">{{ $productReport->reporter_name ?? '-' }} ({{ $productReport->reporter_email ?? '-' }})</dd>
                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9"><span class="badge {{ $productReport->status === 'pending' ? 'bg-warning text-dark' : 'bg-success' }}">{{ ucfirst($productReport->status) }}</span></dd>
                        <dt class="col-sm-3">Reviewed By</dt>
                        <dd class="col-sm-9">{{ optional($productReport->reviewer)->name ?? '-' }}</dd>
                        <dt class="col-sm-3">Review Notes</dt>
                        <dd class="col-sm-9">{{ nl2br(e($productReport->review_notes)) }}</dd>
                        <dt class="col-sm-3">Reviewed At</dt>
                        <dd class="col-sm-9">{{ $productReport->reviewed_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                        <dt class="col-sm-3">Submitted</dt>
                        <dd class="col-sm-9">{{ $productReport->created_at->format('Y-m-d H:i:s') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Review</h5>
                    <form method="POST" action="{{ route('admin.product-reports.update', $productReport) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="pending" {{ $productReport->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="resolved" {{ $productReport->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ $productReport->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Review Notes</label>
                            <textarea name="review_notes" rows="4" class="form-control">{{ old('review_notes', $productReport->review_notes) }}</textarea>
                        </div>
                        <button class="btn btn-primary w-100">Save Review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
