@extends('agent.layouts.agent')

@section('title', 'Add Customer')

@section('content')
<div class="jt-card p-3 p-md-4">
    <h2 class="jt-section-title mb-3">Add Customer</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('agent.customers.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone number</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email (optional)</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Region</label>
                <select name="region_id" class="form-select">
                    <option value="">Select region</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected(old('region_id') == $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Location</label>
                <select name="location_id" class="form-select">
                    <option value="">Select location</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Delivery address</label>
                <textarea name="delivery_address" class="form-control" rows="2">{{ old('delivery_address') }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Save Customer</button>
            <a href="{{ route('agent.customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
