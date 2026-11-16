@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-rectangle-ad me-2" style="color:#f68b1e;"></i>Ad Manager — Overview</h4>
        <div>
            <a href="{{ route('admin.ad-manager.submissions.index') }}" class="btn btn-warning btn-sm">
                <i class="fas fa-inbox me-1"></i> Review queue ({{ $counts['pending'] }})
            </a>
            <a href="{{ route('admin.ad-manager.campaigns.index') }}" class="btn btn-outline-secondary btn-sm">All campaigns</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @php
            $kpis = [
                ['Revenue', number_format($totals['revenue'], 2), 'fa-sack-dollar', '#16a34a'],
                ['Spend', number_format($totals['spend'], 2), 'fa-chart-line', '#f68b1e'],
                ['Impressions', number_format($totals['impressions']), 'fa-eye', '#2563eb'],
                ['Clicks', number_format($totals['clicks']), 'fa-mouse-pointer', '#7c3aed'],
                ['CTR', $ctr.'%', 'fa-percent', '#0891b2'],
                ['Conversions', number_format($totals['conversions']), 'fa-bullseye', '#db2777'],
            ];
        @endphp
        @foreach($kpis as [$label, $value, $icon, $color])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">{{ $label }}</div>
                            <i class="fas {{ $icon }}" style="color:{{ $color }};"></i>
                        </div>
                        <h4 class="mb-0 mt-1">{{ $value }}</h4>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Campaign status</div>
                <div class="card-body">
                    <div class="row g-3 text-center">
                        @foreach(['total' => 'Total', 'pending' => 'Pending review', 'active' => 'Active', 'scheduled' => 'Scheduled', 'paused' => 'Paused', 'rejected' => 'Rejected'] as $k => $label)
                            <div class="col-4">
                                <div class="border rounded-3 py-2">
                                    <div class="fs-4 fw-bold">{{ $counts[$k] }}</div>
                                    <div class="text-muted small">{{ $label }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Placement utilisation</div>
                <div class="card-body">
                    @forelse($byPlacement as $p)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div class="fw-semibold">{{ $p->name }}</div>
                                <small class="text-muted">{{ strtoupper($p->pricing_model) }} · base {{ number_format($p->base_price) }}</small>
                            </div>
                            <span class="badge {{ $p->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $p->campaigns_count }} active
                            </span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No placements defined.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Top campaigns by impressions</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Campaign</th><th>Vendor</th><th class="text-end">Impr.</th>
                                <th class="text-end">Clicks</th><th class="text-end">CTR</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topCampaigns as $c)
                                <tr>
                                    <td><a href="{{ route('admin.ad-manager.campaigns.show', $c->id) }}">{{ $c->title }}</a></td>
                                    <td>{{ $c->vendor?->shop_name ?? $c->vendor?->name ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($c->impressions) }}</td>
                                    <td class="text-end">{{ number_format($c->clicks) }}</td>
                                    <td class="text-end">{{ $c->ctr }}%</td>
                                    <td><span class="badge {{ $c->statusBadgeClass() }}">{{ $c->statusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No campaigns yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span>Recent submissions</span>
                    <a href="{{ route('admin.ad-manager.submissions.index') }}" class="small">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($recentPending as $c)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <a href="{{ route('admin.ad-manager.submissions.show', $c->id) }}" class="fw-semibold">{{ $c->title }}</a>
                                <div class="small text-muted">{{ $c->vendor?->shop_name ?? '-' }} · {{ optional($c->submitted_at)->diffForHumans() }}</div>
                            </div>
                            <span class="badge {{ $c->statusBadgeClass() }}">{{ $c->statusLabel() }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Nothing awaiting review.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
