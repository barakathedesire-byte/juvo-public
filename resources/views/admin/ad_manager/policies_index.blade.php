@extends('admin.layouts.admin')

@section('content')
    <div class="mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-shield-alt me-2" style="color:#f68b1e;"></i>Advertising Policy Reviews</h4>
        <p class="text-muted small mb-0">Immutable record of every content policy decision.</p>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>When</th><th>Campaign</th><th>Vendor</th><th>Decision</th><th>Reason</th><th>Reviewer</th><th>Note</th></tr>
                </thead>
                <tbody>
                    @forelse($reviews as $r)
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td>{{ $r->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $r->campaign->title ?? '—' }}</td>
                            <td>{{ $r->campaign->vendor->name ?? '—' }}</td>
                            <td><span class="badge {{ $r->decision === 'approved' ? 'text-bg-success' : ($r->decision === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $r->decision }}</span></td>
                            <td><code>{{ $r->reason_code ?? '—' }}</code></td>
                            <td>{{ $r->reviewer_id ?? '—' }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($r->note, 60) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No policy reviews recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $reviews->links() }}</div>
    </div>
@endsection
