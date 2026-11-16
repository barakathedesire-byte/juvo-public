@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h4 class="mb-0"><i class="fas fa-triangle-exclamation me-2" style="color:#f68b1e;"></i>Marketplace Anomalies</h4>
            <p class="text-muted mb-0 small">Detection is read-only. Resolving is a human decision — nothing is auto-remediated.</p>
        </div>
        <a href="{{ route('admin.intelligence.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Intelligence</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Anomalies</div>
                <form method="GET" class="row g-2 p-3 border-bottom">
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="severity" class="form-select form-select-sm">
                            <option value="">All severities</option>
                            <option value="warning" @selected(request('severity') === 'warning')>warning</option>
                            <option value="critical" @selected(request('severity') === 'critical')>critical</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-sm btn-outline-primary">Filter</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Metric</th><th>Severity</th><th>Observed / Expected</th><th>Status</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                            @forelse($anomalies as $anomaly)
                                <tr>
                                    <td>
                                        <span class="fw-semibold">{{ $anomaly->metric }}</span>
                                        <div class="small text-muted">{{ $anomaly->rule?->name }} · {{ optional($anomaly->detected_at)->toDateString() }}</div>
                                    </td>
                                    <td><span class="badge {{ $anomaly->severity === 'critical' ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $anomaly->severity }}</span></td>
                                    <td class="small">
                                        {{ number_format($anomaly->observed_value, 2) }} /
                                        {{ number_format($anomaly->expected_value, 2) }}
                                        @if($anomaly->deviation)<span class="text-muted">({{ number_format($anomaly->deviation * 100, 1) }}%)</span>@endif
                                    </td>
                                    <td><span class="badge text-bg-light border">{{ $anomaly->status }}</span></td>
                                    <td class="text-end">
                                        @if($anomaly->status === 'open')
                                            <form method="POST" action="{{ route('admin.intelligence.anomalies.acknowledge', $anomaly) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary">Ack</button>
                                            </form>
                                        @endif
                                        @if(!in_array($anomaly->status, ['resolved', 'false_positive'], true))
                                            <form method="POST" action="{{ route('admin.intelligence.anomalies.resolve', $anomaly) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success">Resolve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.intelligence.anomalies.false-positive', $anomaly) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger">False +</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No anomalies found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $anomalies->withQueryString()->links() }}
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Detection rules</div>
                <ul class="list-group list-group-flush">
                    @foreach($rules as $rule)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">{{ $rule->name }}</span>
                                <span class="badge {{ $rule->is_enabled ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $rule->is_enabled ? 'on' : 'off' }}</span>
                            </div>
                            <div class="small text-muted">
                                {{ $rule->metric }} · {{ $rule->method }} · window {{ $rule->window }}d · min {{ $rule->min_sample }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
