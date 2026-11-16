@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h4 class="mb-0"><i class="fas fa-microchip me-2" style="color:#f68b1e;"></i>Algorithm Engine</h4>
            <p class="text-muted mb-0 small">Every algorithm the marketplace can run, with activation and configuration governance.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small text-uppercase">Impressions (30d)</div>
                <div class="fs-4 fw-semibold">{{ number_format($totals['impressions'] ?? 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small text-uppercase">Clicks (30d)</div>
                <div class="fs-4 fw-semibold">{{ number_format($totals['clicks'] ?? 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small text-uppercase">CTR (30d)</div>
                <div class="fs-4 fw-semibold">{{ number_format(($totals['ctr'] ?? 0) * 100, 2) }}%</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small text-uppercase">Attributed purchases (30d)</div>
                <div class="fs-4 fw-semibold">{{ number_format($totals['purchases'] ?? 0) }}</div>
            </div></div>
        </div>
    </div>

    @foreach($groups as $category => $items)
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold text-uppercase small">{{ $category }}</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Algorithm</th><th>Implementation</th><th>Status</th><th>Version</th><th class="text-end"></th></tr>
                    </thead>
                    <tbody>
                        @foreach($items as $algorithm)
                            <tr>
                                <td>
                                    <i class="fas {{ $algorithm->meta['icon'] ?? 'fa-circle-nodes' }} me-2 text-muted"></i>
                                    <span class="fw-semibold">{{ $algorithm->name }}</span>
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($algorithm->description, 110) }}</div>
                                </td>
                                <td>
                                    @if($algorithm->is_implemented)
                                        <span class="badge text-bg-success">Implemented</span>
                                    @else
                                        <span class="badge text-bg-light border">Planned</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $algorithm->isActive() ? 'text-bg-success' : ($algorithm->status === 'disabled' ? 'text-bg-secondary' : 'text-bg-warning') }}">
                                        {{ ucfirst($algorithm->status) }}
                                    </span>
                                </td>
                                <td>v{{ $algorithm->version }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.algorithms.show', $algorithm) }}" class="btn btn-sm btn-outline-primary">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
