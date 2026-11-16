@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-box-open me-2" style="color:#f68b1e;"></i>Pricing &amp; Packages</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newPackage"><i class="fas fa-plus me-1"></i>New package</button>
    </div>

    <div class="collapse mb-3" id="newPackage">
        <div class="card shadow-sm"><div class="card-body">
            <form method="POST" action="{{ route('admin.ad-manager.packages.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3"><input name="name" class="form-control" placeholder="Package name" required></div>
                <div class="col-md-3">
                    <select name="placement_id" class="form-select">
                        <option value="">No placement</option>
                        @foreach($placements as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2"><input name="duration_days" type="number" class="form-control" value="30" placeholder="Days" required></div>
                <div class="col-md-2"><input name="price" type="number" step="0.01" class="form-control" placeholder="Price" required></div>
                <div class="col-md-2"><input name="impressions_included" type="number" class="form-control" placeholder="Impressions"></div>
                <div class="col-12"><input name="description" class="form-control" placeholder="Description"></div>
                <div class="col-12"><button class="btn btn-primary">Create package</button></div>
            </form>
        </div></div>
    </div>

    <div class="row g-3">
        @forelse($packages as $pkg)
            <div class="col-md-4">
                <div class="card shadow-sm h-100 {{ $pkg->is_active ? '' : 'opacity-50' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-1">{{ $pkg->name }}</h5>
                            <span class="badge {{ $pkg->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $pkg->is_active ? 'Active' : 'Off' }}</span>
                        </div>
                        <div class="text-muted small mb-2">{{ $pkg->placement?->name ?? 'Any placement' }}</div>
                        <div class="fs-4 fw-bold">{{ number_format($pkg->price, 2) }}</div>
                        <ul class="list-unstyled small mt-2 mb-3">
                            <li><i class="fas fa-clock me-1"></i>{{ $pkg->duration_days }} days</li>
                            <li><i class="fas fa-eye me-1"></i>{{ number_format($pkg->impressions_included) }} impressions</li>
                        </ul>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('admin.ad-manager.packages.toggle', $pkg->id) }}">@csrf
                                <button class="btn btn-sm btn-outline-secondary">{{ $pkg->is_active ? 'Disable' : 'Enable' }}</button></form>
                            <form method="POST" action="{{ route('admin.ad-manager.packages.destroy', $pkg->id) }}"
                                  onsubmit="return confirm('Delete this package?');">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-light border text-center text-muted">No packages defined.</div></div>
        @endforelse
    </div>
@endsection
