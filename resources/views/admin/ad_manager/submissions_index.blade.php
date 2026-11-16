@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-inbox me-2" style="color:#f68b1e;"></i>Ad Submissions</h4>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach(['submitted' => 'New', 'under_review' => 'In review', 'changes_requested' => 'Changes requested', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $status === $key ? 'active' : '' }}" href="{{ route('admin.ad-manager.submissions.index', ['status' => $key]) }}">
                    {{ $label }}
                    @isset($queueCounts[$key])<span class="badge text-bg-light ms-1">{{ $queueCounts[$key] }}</span>@endisset
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Campaign</th><th>Vendor</th><th>Placement</th>
                        <th class="text-end">Budget</th><th>Priority</th><th>Submitted</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $c)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td>
                                <a href="{{ route('admin.ad-manager.submissions.show', $c->id) }}" class="fw-semibold">{{ $c->title }}</a>
                                <div class="small text-muted">{{ $c->product?->translation?->name ?? '—' }}</div>
                            </td>
                            <td>{{ $c->vendor?->shop_name ?? $c->vendor?->name ?? '-' }}</td>
                            <td>{{ $c->placement?->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($c->amount_paid) }}</td>
                            <td>
                                @if($c->priority > 0)<span class="badge text-bg-danger">High</span>@else<span class="text-muted">—</span>@endif
                            </td>
                            <td class="small text-muted">{{ optional($c->submitted_at)->diffForHumans() ?? '—' }}</td>
                            <td><span class="badge {{ $c->statusBadgeClass() }}">{{ $c->statusLabel() }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.ad-manager.submissions.show', $c->id) }}" class="btn btn-sm btn-outline-primary">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No submissions in this queue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $campaigns->links() }}</div>
@endsection
