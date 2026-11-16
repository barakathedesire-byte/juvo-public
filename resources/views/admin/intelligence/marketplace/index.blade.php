@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h4 class="mb-0"><i class="fas fa-chart-pie me-2" style="color:#f68b1e;"></i>Marketplace Intelligence</h4>
            <p class="text-muted mb-0 small">Marketplace health, discovery performance and open anomalies.</p>
        </div>
        <div>
            <a href="{{ route('admin.intelligence.anomalies') }}" class="btn btn-outline-danger btn-sm">
                Anomalies <span class="badge text-bg-danger">{{ $openAnomalies->count() }}</span>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Discovery impressions</div>
            <div class="fs-4 fw-semibold">{{ number_format($totals['impressions'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">CTR</div>
            <div class="fs-4 fw-semibold">{{ number_format(($totals['ctr'] ?? 0) * 100, 2) }}%</div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Add-to-cart rate</div>
            <div class="fs-4 fw-semibold">{{ number_format(($totals['atc_rate'] ?? 0) * 100, 2) }}%</div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Attributed revenue</div>
            <div class="fs-4 fw-semibold">{{ number_format(($totals['revenue_minor'] ?? 0) / 100, 2) }}</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Marketplace metric snapshots</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Metric</th><th>Latest</th><th>Days</th><th>Updated</th></tr></thead>
                        <tbody>
                            @foreach($metricLabels as $key => $label)
                                @php $snap = ($metrics[$key] ?? collect()); @endphp
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="fw-semibold">{{ number_format((float) optional($snap->last())->value, 2) }}</td>
                                    <td>{{ $snap->count() }}</td>
                                    <td class="small text-muted">{{ optional(optional($snap->last())->period_date)->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Product associations</span>
                    <form method="POST" action="{{ route('admin.intelligence.associations.rebuild') }}" class="d-flex gap-2 align-items-center">
                        @csrf
                        <input type="number" name="min_support" value="2" min="1" class="form-control form-control-sm" style="width:80px" title="Minimum co-purchase / co-view support">
                        <button class="btn btn-sm btn-outline-primary">Rebuild</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span class="text-muted">Similar pairs</span><span class="fw-semibold">{{ number_format($associations['similar']) }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Complementary pairs</span><span class="fw-semibold">{{ number_format($associations['complementary']) }}</span></div>
                    <div class="text-muted small mt-2">Last rebuilt: {{ $associations['last_computed'] ? \Illuminate\Support\Carbon::parse($associations['last_computed'])->diffForHumans() : 'never' }}</div>
                    <div class="text-muted small">Complementary rails only appear with real co-purchase evidence.</div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Discovery by surface (30d)</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Surface</th><th>Impr.</th><th>CTR</th><th>ATC</th><th>Purch.</th><th>Revenue</th></tr></thead>
                        <tbody>
                            @forelse($surfaceMetrics as $row)
                                <tr>
                                    <td>{{ $row['surface'] }}</td>
                                    <td>{{ number_format($row['impressions']) }}</td>
                                    <td>{{ number_format($row['ctr'] * 100, 2) }}%</td>
                                    <td>{{ number_format($row['atc_rate'] * 100, 2) }}%</td>
                                    <td>{{ number_format($row['purchases']) }}</td>
                                    <td>{{ number_format($row['revenue_minor'] / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted small">No events recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Render health</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Surface</th><th>Renders</th><th>Empty</th><th>Avg ms</th></tr></thead>
                        <tbody>
                            @forelse($telemetry as $row)
                                <tr>
                                    <td>{{ $row['surface'] }}</td>
                                    <td>{{ number_format($row['renders']) }}</td>
                                    <td>{{ number_format($row['empty_rate'] * 100, 1) }}%</td>
                                    <td>{{ $row['avg_latency_ms'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted small">No render telemetry yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Open anomalies</div>
                <div class="list-group list-group-flush">
                    @forelse($openAnomalies as $anomaly)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">{{ $anomaly->metric }}</span>
                                <span class="badge {{ $anomaly->severity === 'critical' ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $anomaly->severity }}</span>
                            </div>
                            <div class="small text-muted">
                                observed {{ number_format($anomaly->observed_value, 2) }} vs expected {{ number_format($anomaly->expected_value, 2) }}
                                · {{ optional($anomaly->detected_at)->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted small">No open anomalies.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
