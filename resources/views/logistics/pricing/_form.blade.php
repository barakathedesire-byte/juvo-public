<div class="modal-header"><h5 class="modal-title">{{ $rule ? 'Edit pricing rule' : 'New pricing rule' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $rule?->name) }}"></div>
        <div class="col-md-3"><label class="form-label">Zone</label><select name="zone_id" class="form-select"><option value="">All</option>@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected(old('zone_id', $rule?->zone_id)==$zone->id)>{{ $zone->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Location</label><select name="location_id" class="form-select"><option value="">All</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id', $rule?->location_id)==$location->id)>{{ $location->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Delivery type</label><select name="delivery_type" class="form-select" required><option value="door" @selected(old('delivery_type', $rule?->delivery_type)=='door')>Door</option><option value="pickup" @selected(old('delivery_type', $rule?->delivery_type)=='pickup')>Pickup</option></select></div>
        <div class="col-md-4"><label class="form-label">Vehicle type</label><input name="vehicle_type" class="form-control" value="{{ old('vehicle_type', $rule?->vehicle_type) }}"></div>
        <div class="col-md-4"><label class="form-label">Priority</label><input type="number" name="priority" class="form-control" value="{{ old('priority', $rule?->priority ?? 100) }}"></div>
        <div class="col-md-3"><label class="form-label">Base fee</label><input type="number" step="0.01" name="base_fee" class="form-control" required value="{{ old('base_fee', $rule?->base_fee ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Per km fee</label><input type="number" step="0.01" name="per_km_fee" class="form-control" value="{{ old('per_km_fee', $rule?->per_km_fee ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Minimum fee</label><input type="number" step="0.01" name="minimum_fee" class="form-control" value="{{ old('minimum_fee', $rule?->minimum_fee ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Peak surcharge</label><input type="number" step="0.01" name="peak_surcharge" class="form-control" value="{{ old('peak_surcharge', $rule?->peak_surcharge ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Handling surcharge</label><input type="number" step="0.01" name="handling_surcharge" class="form-control" value="{{ old('handling_surcharge', $rule?->handling_surcharge ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Free delivery over</label><input type="number" step="0.01" name="free_delivery_threshold" class="form-control" value="{{ old('free_delivery_threshold', $rule?->free_delivery_threshold) }}"></div>
        <div class="col-md-3"><label class="form-label">Effective from</label><input type="date" name="effective_from" class="form-control" value="{{ old('effective_from', optional($rule?->effective_from)->format('Y-m-d')) }}"></div>
        <div class="col-md-3"><label class="form-label">Effective to</label><input type="date" name="effective_to" class="form-control" value="{{ old('effective_to', optional($rule?->effective_to)->format('Y-m-d')) }}"></div>
        <div class="col-12"><label class="form-label">Distance tiers JSON</label><textarea name="distance_tiers" class="form-control" rows="2">{{ old('distance_tiers', $rule?->distance_tiers ? json_encode($rule->distance_tiers) : '') }}</textarea></div>
        <div class="col-12 form-check ms-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="pricingActive{{ $rule?->id }}" @checked(old('is_active', $rule?->is_active ?? true))><label class="form-check-label" for="pricingActive{{ $rule?->id }}">Active</label></div>
    </div>
</div>
