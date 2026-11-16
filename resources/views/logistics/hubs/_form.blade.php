<div class="modal-header"><h5 class="modal-title">{{ $hub ? 'Edit hub' : 'New hub' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $hub?->name) }}"></div>
        <div class="col-md-6"><label class="form-label">Code</label><input name="code" class="form-control" required value="{{ old('code', $hub?->code) }}"></div>
        <div class="col-md-6"><label class="form-label">Region</label><select name="region_id" class="form-select" id="hubRegionSelect"><option value="">All regions</option>@foreach(($regions ?? []) as $region)<option value="{{ $region->id }}" @selected(old('region_id', $hub?->location?->region_id)==$region->id)>{{ $region->name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Location</label><select name="location_id" class="form-select" id="hubLocationSelect"><option value="">—</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-region-id="{{ $location->region_id }}" @selected(old('location_id', $hub?->location_id)==$location->id)>{{ $location->name }} ({{ $location->region?->name }})</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Zone</label><select name="zone_id" class="form-select"><option value="">—</option>@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected(old('zone_id', $hub?->zone_id)==$zone->id)>{{ $zone->name }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', $hub?->address) }}"></div>
        <div class="col-md-6"><label class="form-label">Latitude</label><input type="number" step="0.0000001" name="latitude" class="form-control" value="{{ old('latitude', $hub?->latitude) }}"></div>
        <div class="col-md-6"><label class="form-label">Longitude</label><input type="number" step="0.0000001" name="longitude" class="form-control" value="{{ old('longitude', $hub?->longitude) }}"></div>
        <div class="col-md-6"><label class="form-label">Operating hours</label><input name="operating_hours" class="form-control" value="{{ old('operating_hours', $hub?->operating_hours) }}"></div>
        <div class="col-md-6"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control" value="{{ old('capacity', $hub?->capacity) }}"></div>
        <div class="col-12 form-check ms-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="hubActive{{ $hub?->id }}" @checked(old('is_active', $hub?->is_active ?? true))><label class="form-check-label" for="hubActive{{ $hub?->id }}">Active</label></div>
    </div>
</div>
