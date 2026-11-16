@extends('logistics.layouts.app')

@section('title', 'Pricing Revenue')
@section('heading', 'Pricing revenue')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Pricing</span><span aria-hidden="true"> / </span><span>Revenue</span>
@endsection

@section('content')
<div class="page-header"><div><span class="page-eyebrow">Revenue</span><h2>Pricing revenue intelligence</h2><p>Revenue metrics from actual order delivery fees.</p></div></div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Total delivery revenue</div><div class="stat-value">TZS {{ number_format($revenue, 2) }}</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Average delivery fee</div><div class="stat-value">TZS {{ number_format($average, 2) }}</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Active pricing rules</div><div class="stat-value">{{ $rules->where('is_active', true)->count() }}</div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Region/City</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody>
                @forelse($byZone as $row)
                    <tr><td>{{ $row->state }} / {{ $row->city }}</td><td>{{ $row->orders }}</td><td>TZS {{ number_format((float)$row->revenue, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty-state">No delivery revenue yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
