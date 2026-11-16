<div class="modal fade" id="editRegionModal-{{ $region->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.regions.update', $region) }}">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Region</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name', $region->name) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="{{ old('country', $region->country) }}">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $region->sort_order) }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3 form-check mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" class="form-check-input" id="region_active_{{ $region->id }}" value="1" {{ old('is_active', $region->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="region_active_{{ $region->id }}">Active</label>
                        </div>
                    </div>
                </div>
                @php($dep = $regionDependencies[$region->id] ?? null)
                @if($dep && !$region->is_active && $dep['orders'] > 0)
                    <div class="alert alert-warning small mb-0">
                        Deactivating hides this region from the storefront and checkout, but {{ $dep['orders'] }} existing
                        order(s) keep their recorded delivery address.
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
