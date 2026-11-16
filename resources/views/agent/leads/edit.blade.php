@extends('agent.layouts.agent')

@section('title', 'Edit Lead')

@section('content')
<div class="jt-card p-3 p-md-4">
    <h2 class="jt-section-title mb-3">Edit Lead</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('agent.leads.update', $lead) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $lead->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone number</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $lead->phone) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email (optional)</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $lead->email) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Region</label>
                <select name="region_id" class="form-select">
                    <option value="">Select region</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected(old('region_id', $lead->region_id) == $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Location</label>
                <select name="location_id" class="form-select">
                    <option value="">Select location</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $lead->location_id) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lead source</label>
                <select name="source" class="form-select" required>
                    @foreach (\App\Models\SalesLead::SOURCES as $key => $label)
                        <option value="{{ $key }}" @selected(old('source', $lead->source) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Interest</label>
                <input type="text" name="interest" class="form-control" value="{{ old('interest', $lead->interest) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select">
                    <option value="">Select category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $lead->category_id) == $category->id)>{{ $category->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Product of interest</label>
                <select name="product_id" class="form-select">
                    <option value="">Select product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id', $lead->product_id) == $product->id)>{{ $product->translation?->name ?: 'Product #'.$product->id }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Budget</label>
                <input type="number" step="0.01" name="budget" class="form-control" value="{{ old('budget', $lead->budget) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Purchase timeline</label>
                <input type="text" name="timeline" class="form-control" value="{{ old('timeline', $lead->timeline) }}">
            </div>
            <div class="col-12">
                <label class="form-label">Customer needs</label>
                <textarea name="needs" class="form-control" rows="3">{{ old('needs', $lead->needs) }}</textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    @foreach (['new','contacted','interested','follow_up','lost'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $lead->status) === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lost reason</label>
                <select name="lost_reason" class="form-select">
                    <option value="">None</option>
                    @foreach (\App\Models\SalesLead::LOST_REASONS as $key => $label)
                        <option value="{{ $key }}" @selected(old('lost_reason', $lead->lost_reason) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Next follow-up</label>
                <input type="datetime-local" name="next_followup_at" class="form-control" value="{{ old('next_followup_at', $lead->next_followup_at?->format('Y-m-d\TH:i')) }}">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Update Lead</button>
            <a href="{{ route('agent.leads.show', $lead) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
