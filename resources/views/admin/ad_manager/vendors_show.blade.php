@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">{{ $vendor->shop_name ?? $vendor->name }} — Advertising account</h4>
        <a href="{{ route('admin.ad-manager.vendors.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="row g-3 mb-3">
        @foreach([
            ['Campaigns', $stats['campaigns']],
            ['Active', $stats['active']],
            ['Spend', number_format($stats['spend'], 2)],
            ['Impressions', number_format($stats['impressions'])],
            ['Clicks', number_format($stats['clicks'])],
        ] as [$label, $value])
            <div class="col-6 col-md">
                <div class="card shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-5 fw-bold">{{ $value }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Account controls</div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="mb-1">Advertising access:
                            @if($vendor->ad_restricted)<span class="badge text-bg-danger">Restricted</span>
                            @else<span class="badge text-bg-success">Allowed</span>@endif
                        </div>
                        <form method="POST" action="{{ route('admin.ad-manager.vendors.restrict', $vendor->id) }}">@csrf
                            <button class="btn btn-sm {{ $vendor->ad_restricted ? 'btn-outline-success' : 'btn-outline-danger' }}">
                                {{ $vendor->ad_restricted ? 'Restore advertising' : 'Restrict advertising' }}
                            </button>
                        </form>
                    </div>
                    <form method="POST" action="{{ route('admin.ad-manager.vendors.credit-limit', $vendor->id) }}">
                        @csrf
                        <label class="form-label small">Credit limit</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="ad_credit_limit" class="form-control" value="{{ $vendor->ad_credit_limit }}">
                            <button class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Campaign history</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Campaign</th><th>Placement</th><th class="text-end">Spent</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($campaigns as $c)
                                <tr>
                                    <td>{{ $c->id }}</td>
                                    <td><a href="{{ route('admin.ad-manager.campaigns.show', $c->id) }}">{{ $c->title }}</a></td>
                                    <td>{{ $c->placement?->name ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($c->spent, 2) }}</td>
                                    <td><span class="badge {{ $c->statusBadgeClass() }}">{{ $c->statusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No campaigns.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
