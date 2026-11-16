@extends('admin.layouts.admin')
@section('title', 'Regions & Locations')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Regions & Locations</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRegionModal">
            <i class="fas fa-plus me-1"></i> Add Region
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        @forelse($regions as $region)
            @php($dep = $regionDependencies[$region->id] ?? ['locations' => 0, 'orders' => 0])
            <div class="col-12 col-xl-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <h5 class="mb-1">{{ $region->name }}</h5>
                            <div class="text-muted small">{{ $region->country }}@if(!$region->is_active) <span class="badge bg-secondary ms-1">Inactive</span>@endif</div>
                            <div class="mt-2">
                                <span class="badge bg-light text-dark border">{{ $dep['locations'] }} locations</span>
                                <span class="badge bg-light text-dark border">{{ $dep['orders'] }} orders</span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editRegionModal-{{ $region->id }}">Edit</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createLocationModal-{{ $region->id }}">Add Location</button>
                            <form method="POST" action="{{ route('admin.regions.destroy', $region) }}" class="d-inline"
                                  onsubmit="return confirm('Delete the region &quot;{{ $region->name }}&quot;? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($region->locations->isEmpty())
                            <p class="text-muted mb-0">No locations for this region.</p>
                        @else
                            <div class="row g-2">
                                @foreach($region->locations()->ordered()->get() as $location)
                                    <div class="col-12 col-md-6">
                                        <div class="border rounded p-2 h-100">
                                            <button type="button" class="btn btn-link text-decoration-none p-0 fw-bold" data-bs-toggle="modal" data-bs-target="#editLocationModal-{{ $location->id }}">
                                                {{ $location->name }}
                                                @if(!$location->is_active)<span class="badge bg-secondary ms-1">Inactive</span>@endif
                                            </button>
                                            <div class="small text-muted">{{ $location->postal_code ?: 'No postal code' }}</div>
                                        </div>
                                    </div>
                                    @include('admin.regions.modals.edit_location', ['location' => $location])
                                    <form id="deleteLocationForm-{{ $location->id }}" method="POST" action="{{ route('admin.locations.destroy', $location) }}" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @include('admin.regions.modals.edit_region', ['region' => $region])
            @include('admin.regions.modals.create_location', ['region' => $region])
        @empty
            <div class="col-12">
                <div class="alert alert-info mb-0">No regions yet. Add one to get started.</div>
            </div>
        @endforelse
    </div>
</div>

@include('admin.regions.modals.create_region')
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (modal) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    @if (session('region_create_error'))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('createRegionModal')).show();
    @endif
    @foreach ($regions as $region)
        @if (session('region_edit_'.$region->id))
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editRegionModal-{{ $region->id }}')).show();
        @endif
        @if (session('location_create_'.$region->id))
            bootstrap.Modal.getOrCreateInstance(document.getElementById('createLocationModal-{{ $region->id }}')).show();
        @endif
        @foreach ($region->locations as $loc)
            @if (session('location_edit_'.$loc->id))
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editLocationModal-{{ $loc->id }}')).show();
            @endif
        @endforeach
    @endforeach
});
</script>
@endsection
