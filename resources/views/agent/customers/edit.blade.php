@extends('agent.layouts.agent')

@section('title', 'Edit Customer')

@section('content')
<div class="jt-card p-3 p-md-4">
    <h2 class="jt-section-title mb-3">Edit Customer</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('agent.customers.update', $customer) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $customer->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone number</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email (optional)</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Region</label>
                <select name="region_id" class="form-select">
                    <option value="">Select region</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected(old('region_id', $customer->region_id) == $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Location</label>
                <select name="location_id" class="form-select">
                    <option value="">Select location</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $customer->location_id) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2">{{ old('address', $customer->address) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Delivery address</label>
                <textarea name="delivery_address" class="form-control" rows="2">{{ old('delivery_address', $customer->delivery_address) }}</textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">CRM status</label>
                <select name="crm_status" class="form-select">
                    @foreach (['new','active','repeat','inactive','high_value'] as $status)
                        <option value="{{ $status }}" @selected(old('crm_status', $customer->crm_status) === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Update Customer</button>
            <a href="{{ route('agent.customers.show', $customer) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
