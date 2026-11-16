<div class="modal-header"><h5 class="modal-title">{{ $zone ? 'Edit delivery zone' : 'New delivery zone' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $zone?->name) }}"></div>
        <div class="col-md-6"><label class="form-label">Code</label><input name="code" class="form-control" required value="{{ old('code', $zone?->code) }}"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2">{{ old('description', $zone?->description) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Primary hub</label><select name="primary_hub_id" class="form-select"><option value="">—</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected(old('primary_hub_id', $zone?->primary_hub_id)==$hub->id)>{{ $hub->name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control" value="{{ old('capacity', $zone?->capacity) }}"></div>
        <div class="col-md-6"><label class="form-label">Locations</label><select name="location_ids[]" class="form-select" multiple size="6">@foreach($locations as $location)<option value="{{ $location->id }}" @selected(in_array($location->id, old('location_ids', $zone?->locations->pluck('id')->all() ?? [])))>{{ $location->name }} ({{ $location->region?->name }})</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Riders</label><select name="rider_ids[]" class="form-select" multiple size="6">@foreach($riders as $rider)<option value="{{ $rider->id }}" @selected(in_array($rider->id, old('rider_ids', $zone?->riders->pluck('id')->all() ?? [])))>{{ $rider->full_name }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label">Polygon JSON (array of [lat,lng])</label><textarea name="polygon" class="form-control" rows="3">{{ old('polygon', $zone?->polygon ? json_encode($zone->polygon) : '') }}</textarea></div>
        <div class="col-12"><label class="form-label">Service restrictions</label><textarea name="service_restrictions" class="form-control" rows="2">{{ old('service_restrictions', $zone?->service_restrictions) }}</textarea></div>
        <div class="col-12 form-check ms-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="zoneActive{{ $zone?->id }}" @checked(old('is_active', $zone?->is_active ?? true))><label class="form-check-label" for="zoneActive{{ $zone?->id }}">Active</label></div>
    </div>
</div>
