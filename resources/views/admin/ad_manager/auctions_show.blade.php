@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-gavel me-2" style="color:#f68b1e;"></i>Auction #{{ $decision->id }} explanation</h4>
        <a href="{{ route('admin.ad-manager.auctions') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row small">
                <div class="col-md-3"><strong>Placement:</strong> <code>{{ $decision->placement_key }}</code></div>
                <div class="col-md-3"><strong>Algorithm:</strong> {{ $explanation['algorithm_version'] }}</div>
                <div class="col-md-3"><strong>Context hash:</strong> <code>{{ $explanation['context_hash'] }}</code></div>
                <div class="col-md-3"><strong>Winners:</strong> {{ implode(', ', $explanation['winners']) ?: 'none' }}</div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Campaign</th><th>Eligible</th><th class="text-end">Score</th><th class="text-end">Bid</th>
                        <th>Model</th><th class="text-end">eCPM</th><th class="text-end">Pacing</th><th>Reasons</th></tr>
                </thead>
                <tbody>
                    @foreach(($explanation['candidates'] ?? []) as $c)
                        <tr class="{{ $c['eligible'] ? '' : 'table-warning' }}">
                            <td>#{{ $c['campaign_id'] }}</td>
                            <td><span class="badge {{ $c['eligible'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $c['eligible'] ? 'yes' : 'no' }}</span></td>
                            <td class="text-end">{{ number_format($c['score'], 2) }}</td>
                            <td class="text-end">{{ number_format(($c['bid_minor'] ?? 0) / 100, 2) }}</td>
                            <td>{{ $c['billing_model'] ?? '—' }}</td>
                            <td class="text-end">{{ number_format(($c['expected_cpm_minor'] ?? 0) / 100, 2) }}</td>
                            <td class="text-end">{{ $c['pacing_multiplier'] ?? '—' }}</td>
                            <td class="small">{{ implode(', ', $c['reasons'] ?? []) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
