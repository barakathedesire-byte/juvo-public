@extends('logistics.layouts.app')

@section('title', 'Pricing Simulator')
@section('heading', 'Pricing simulator')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Pricing</span><span aria-hidden="true"> / </span><span>Simulator</span>
@endsection

@section('content')
<div class="page-header"><div><span class="page-eyebrow">Revenue</span><h2>Pricing simulator</h2><p>Simulate delivery fees without changing live prices.</p></div></div>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.pricing.simulate.run') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4"><label class="form-label">Location</label><select name="location_id" class="form-select" required><option value="">Select</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id', request('location_id'))==$location->id)>{{ $location->name }} ({{ $location->region?->name }})</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Delivery type</label><select name="delivery_type" class="form-select"><option value="door">Door</option><option value="pickup">Pickup</option></select></div>
            <div class="col-md-2"><label class="form-label">Vehicle</label><input name="vehicle_type" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Distance km</label><input type="number" step="0.01" name="distance_km" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Subtotal</label><input type="number" step="0.01" name="subtotal" class="form-control"></div>
            <div class="col-12"><button class="btn btn-juvo">Simulate</button></div>
        </form>
    </div>
</div>

@if($result)
    <div class="card">
        <div class="card-body">
            <div class="stat-card mb-3"><div class="stat-label">Final delivery fee</div><div class="stat-value">TZS {{ number_format($result['fee'], 2) }}</div><div class="text-muted small">{{ $result['explanation'] }}</div></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Component</th><th>Amount</th></tr></thead>
                    <tbody>
                        @foreach($result['breakdown'] as $key => $value)
                            <tr><td>{{ \Illuminate\Support\Str::headline($key) }}</td><td>{{ number_format((float)$value, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
