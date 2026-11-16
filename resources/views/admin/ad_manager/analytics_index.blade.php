@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-chart-column me-2" style="color:#f68b1e;"></i>Advertising Analytics</h4>
    </div>

    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-2"><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
        <div class="col-md-2"><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
        <div class="col-md-3">
            <select name="placement_id" class="form-select">
                <option value="">All placements</option>
                @foreach($placements as $p)<option value="{{ $p->id }}" @selected($placementId == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Apply</button></div>
    </form>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Daily trend</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Date</th><th class="text-end">Impressions</th><th class="text-end">Clicks</th><th class="text-end">Conversions</th><th class="text-end">Spend</th></tr></thead>
                <tbody>
                    @forelse($trend as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td class="text-end">{{ number_format($row->impressions) }}</td>
                            <td class="text-end">{{ number_format($row->clicks) }}</td>
                            <td class="text-end">{{ number_format($row->conversions) }}</td>
                            <td class="text-end">{{ number_format($row->spend, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No data for this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">By placement</div>
                <ul class="list-group list-group-flush">
                    @foreach($byPlacement as $p)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $p->name }}</span>
                            <span><span class="badge text-bg-primary">{{ $p->campaigns_count }} campaigns</span>
                                <span class="badge text-bg-light">{{ number_format((int) $p->impressions_sum) }} impr.</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Campaign leaderboard (by impressions)</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Campaign</th><th>Vendor</th><th class="text-end">Impr.</th><th class="text-end">Clicks</th><th class="text-end">CTR</th></tr></thead>
                        <tbody>
                            @foreach($leaderboard as $c)
                                <tr>
                                    <td>{{ $c->title }}</td>
                                    <td>{{ $c->vendor?->shop_name ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($c->impressions) }}</td>
                                    <td class="text-end">{{ number_format($c->clicks) }}</td>
                                    <td class="text-end">{{ $c->ctr }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
