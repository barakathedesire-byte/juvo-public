@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Agent Ranks</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sales-agents.index') }}" class="btn btn-sm btn-light">Back</a>
            <form method="POST" action="{{ route('admin.sales-agents.ranks.evaluate') }}">@csrf<button class="btn btn-sm btn-light">Re-evaluate all</button></form>
        </div>
    </div>
    <div class="card-body">
        <h6>Create rank</h6>
        <form action="{{ route('admin.sales-agents.ranks.store') }}" method="POST" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
            <div class="col-md-1"><label class="form-label">Level</label><input type="number" name="level" class="form-control" value="0" required></div>
            <div class="col-md-2"><label class="form-label">Min delivered</label><input type="number" name="min_delivered_orders" class="form-control" value="0"></div>
            <div class="col-md-2"><label class="form-label">Min volume</label><input type="number" step="0.01" name="min_sales_volume" class="form-control" value="0"></div>
            <div class="col-md-2"><label class="form-label">Bonus %</label><input type="number" step="0.01" name="bonus_rate" class="form-control" value="0"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Add rank</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead><tr><th>#</th><th>Name</th><th>Level</th><th>Min delivered</th><th>Min volume</th><th>Bonus %</th><th>Active</th><th></th></tr></thead>
                <tbody>
                    @forelse ($ranks as $rank)
                        <tr>
                            <td>{{ $rank->id }}</td>
                            <td>{{ $rank->name }}</td>
                            <td>{{ $rank->level }}</td>
                            <td>{{ $rank->min_delivered_orders }}</td>
                            <td>{{ number_format((float) $rank->min_sales_volume, 2) }}</td>
                            <td>{{ $rank->bonus_rate }}</td>
                            <td>{!! $rank->is_active ? '<span class="badge bg-success">active</span>' : '<span class="badge bg-secondary">off</span>' !!}</td>
                            <td><form method="POST" action="{{ route('admin.sales-agents.ranks.toggle', $rank->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $rank->is_active ? 'Disable' : 'Enable' }}</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No ranks defined.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header card-header-bg text-white"><h6 class="mb-0">Recent rank changes</h6></div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered">
            <thead><tr><th>Agent</th><th>Rank</th><th>Period</th><th>Volume</th><th>Delivered</th><th>Promoted</th><th>Calculated</th></tr></thead>
            <tbody>
                @forelse ($histories as $history)
                    <tr>
                        <td>{{ $history->agent->name ?? '—' }}</td>
                        <td>{{ $history->rank->name ?? '—' }}</td>
                        <td>{{ $history->period ?? '—' }}</td>
                        <td>{{ number_format((float) $history->sales_volume, 2) }}</td>
                        <td>{{ $history->delivered_orders }}</td>
                        <td>{!! $history->promoted ? '<span class="badge bg-success">yes</span>' : 'no' !!}</td>
                        <td>{{ $history->calculated_at ? \Illuminate\Support\Carbon::parse($history->calculated_at)->format('Y-m-d') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No history.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
