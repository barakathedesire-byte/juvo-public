@extends('admin.layouts.admin')

@section('content')
    <div class="mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-gavel me-2" style="color:#f68b1e;"></i>Auction Decisions</h4>
        <p class="text-muted small mb-0">Every placement request is recorded with its full candidate ranking and reasons.</p>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>When</th><th>Placement</th><th>Algorithm</th><th class="text-end">Candidates</th>
                        <th>Winner</th><th>Context</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($decisions as $d)
                        <tr>
                            <td>{{ $d->id }}</td>
                            <td>{{ $d->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td><code>{{ $d->placement_key }}</code></td>
                            <td>{{ $d->algorithm_version }}</td>
                            <td class="text-end">{{ $d->candidate_count }}</td>
                            <td>{{ $d->winner_campaign_id ? '#'.$d->winner_campaign_id : '—' }}</td>
                            <td><code class="small">{{ \Illuminate\Support\Str::limit($d->context_hash, 16) }}</code></td>
                            <td class="text-end">
                                <a href="{{ route('admin.ad-manager.auctions.show', $d->id) }}" class="btn btn-sm btn-outline-secondary">Explain</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No auction decisions recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $decisions->links() }}</div>
    </div>
@endsection
