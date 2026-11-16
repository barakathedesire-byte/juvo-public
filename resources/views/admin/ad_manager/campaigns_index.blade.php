@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-bullhorn me-2" style="color:#f68b1e;"></i>Campaign Management</h4>
    </div>

    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-3">
            <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search title…">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Campaign</th><th>Vendor</th><th>Placement</th>
                        <th class="text-end">Imp.</th><th class="text-end">Clicks</th>
                        <th class="text-end">Spent</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $c)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td>
                                <a href="{{ route('admin.ad-manager.campaigns.show', $c->id) }}" class="fw-semibold">{{ $c->title }}</a>
                                <div class="small text-muted">{{ $c->product?->translation?->name ?? '—' }}</div>
                            </td>
                            <td>{{ $c->vendor?->shop_name ?? $c->vendor?->name ?? '-' }}</td>
                            <td>{{ $c->placement?->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($c->impressions) }}</td>
                            <td class="text-end">{{ number_format($c->clicks) }}</td>
                            <td class="text-end">{{ number_format($c->spent) }}</td>
                            <td><span class="badge {{ $c->statusBadgeClass() }}">{{ $c->statusLabel() }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.ad-manager.campaigns.show', $c->id) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No campaigns found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $campaigns->links() }}</div>
@endsection
