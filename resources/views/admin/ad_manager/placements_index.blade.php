@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-layer-group me-2" style="color:#f68b1e;"></i>Placements &amp; Inventory</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newPlacement"><i class="fas fa-plus me-1"></i>New placement</button>
    </div>

    <div class="collapse mb-3" id="newPlacement">
        <div class="card shadow-sm"><div class="card-body">
            <form method="POST" action="{{ route('admin.ad-manager.placements.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3"><input name="name" class="form-control" placeholder="Name" required></div>
                <div class="col-md-2"><input name="key" class="form-control" placeholder="key (auto)"></div>
                <div class="col-md-1"><input name="width" class="form-control" placeholder="W" type="number"></div>
                <div class="col-md-1"><input name="height" class="form-control" placeholder="H" type="number"></div>
                <div class="col-md-2"><input name="base_price" class="form-control" placeholder="Base price" type="number" step="0.01"></div>
                <div class="col-md-2">
                    <select name="pricing_model" class="form-select">
                        <option value="cpm">CPM</option><option value="cpc">CPC</option><option value="flat">Flat</option>
                    </select>
                </div>
                <div class="col-md-1"><input name="max_active" class="form-control" placeholder="Max" type="number"></div>
                <div class="col-12"><input name="description" class="form-control" placeholder="Description"></div>
                <div class="col-12"><button class="btn btn-primary">Create placement</button></div>
            </form>
        </div></div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Name</th><th>Key</th><th>Size</th><th>Pricing</th><th class="text-end">Base price</th>
                        <th class="text-end">Active / Max</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($placements as $p)
                        <tr>
                            <td class="fw-semibold">{{ $p->name }}</td>
                            <td><code>{{ $p->key }}</code></td>
                            <td>{{ $p->width && $p->height ? $p->width.'×'.$p->height : '—' }}</td>
                            <td>{{ strtoupper($p->pricing_model) }}</td>
                            <td class="text-end">{{ number_format($p->base_price, 2) }}</td>
                            <td class="text-end">{{ $p->active_campaigns_count }} / {{ $p->max_active ?: '∞' }}</td>
                            <td><span class="badge {{ $p->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $p->is_active ? 'Active' : 'Disabled' }}</span></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.ad-manager.placements.toggle', $p->id) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ $p->is_active ? 'Disable' : 'Enable' }}</button></form>
                                <form method="POST" action="{{ route('admin.ad-manager.placements.destroy', $p->id) }}" class="d-inline"
                                      onsubmit="return confirm('Delete this placement?');">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No placements.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
