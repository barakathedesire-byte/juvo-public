@extends('admin.layouts.admin')

@section('content')
    <div class="mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-robot me-2" style="color:#f68b1e;"></i>Bid Optimisation Runs</h4>
        <p class="text-muted small mb-0">Auto-bidding adjustments, bounded by max CPC and remaining budget.</p>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>When</th><th>Campaign</th><th>Strategy</th><th class="text-end">Before</th>
                        <th class="text-end">After</th><th>Status</th><th>Reason</th></tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td>{{ $run->id }}</td>
                            <td>{{ $run->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $run->campaign->title ?? '—' }}</td>
                            <td>{{ $run->strategy }}</td>
                            <td class="text-end">{{ number_format($run->before_bid_minor / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($run->after_bid_minor / 100, 2) }}</td>
                            <td><span class="badge {{ $run->status === 'applied' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $run->status }}</span></td>
                            <td class="small">{{ $run->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No optimisation runs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $runs->links() }}</div>
    </div>
@endsection
