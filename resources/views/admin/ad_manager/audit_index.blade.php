@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-clipboard-list me-2" style="color:#f68b1e;"></i>Advertising Audit Log</h4>
    </div>

    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-3">
            <select name="action" class="form-select">
                <option value="">All actions</option>
                @foreach($actions as $a)<option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>When</th><th>Campaign</th><th>Action</th><th>Transition</th><th>Admin</th><th>Note</th></tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td class="small text-muted">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                @if($log->campaign)
                                    <a href="{{ route('admin.ad-manager.campaigns.show', $log->campaign_id) }}">{{ $log->campaign->title }}</a>
                                @else—@endif
                            </td>
                            <td><span class="badge text-bg-secondary">{{ $log->action }}</span></td>
                            <td class="small">{{ $log->from_status }} @if($log->to_status)&rarr; {{ $log->to_status }}@endif</td>
                            <td class="small">{{ $log->admin?->name ?? 'system' }}</td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($log->note, 60) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No audit entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
