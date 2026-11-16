<div class="modal fade" id="createLocationModal-{{ $region->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.regions.locations.store', $region) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Add Location to {{ $region->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Postal Code</label>
                    <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code') }}">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Shipping Fee</label>
                            <input type="number" step="0.01" min="0" name="shipping_fee" class="form-control" value="{{ old('shipping_fee', 0) }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Pickup Fee</label>
                            <input type="number" step="0.01" min="0" name="pickup_fee" class="form-control" value="{{ old('pickup_fee') }}">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Free Over</label>
                    <input type="number" step="0.01" min="0" name="free_over" class="form-control" value="{{ old('free_over') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">ETA Label</label>
                    <input type="text" name="eta_label" class="form-control" value="{{ old('eta_label') }}" placeholder="e.g. 1 to 3 days">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Pickup ETA</label>
                            <input type="text" name="pickup_eta" class="form-control" value="{{ old('pickup_eta') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Door ETA</label>
                            <input type="text" name="door_eta" class="form-control" value="{{ old('door_eta') }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3 form-check mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" class="form-check-input" id="loc_active_{{ $region->id }}" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="loc_active_{{ $region->id }}">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
