@extends('admin.layouts.admin')
@section('title', 'Vendor Intelligence')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'Vendor Intelligence',
        'subtitle' => 'Vendor activity, verification, order acceptance, cancellation and fulfillment performance.',
    ])

    <div class="ic-grid k4">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Registered vendors', 'value' => number_format($data['total'])])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Approved', 'value' => number_format($data['approved']), 'hint' => $data['pending'].' pending review'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Active', 'value' => number_format($data['active']), 'hint' => $data['inactive_30d'].' with no orders in 30 days', 'tone' => $data['inactive_30d'] > 0 ? 'warn' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Suspended', 'value' => number_format($data['suspended']), 'tone' => $data['suspended'] > 0 ? 'warn' : 'good'])
    </div>

    @if($data['needs_attention']->count())
    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Vendors requiring review</h3><span class="meta">Based on cancellations, completion and preparation time</span></div>
        <div class="ic-card-body">
            <div class="ic-list">
                @foreach($data['needs_attention'] as $vendor)
                    <div class="ic-list-row">
                        <div class="body">
                            <strong>{{ $vendor['name'] }}</strong>
                            <p>{{ $vendor['canceled'] }} canceled of {{ $vendor['total_orders'] }} orders. Completion {{ $vendor['completion_rate'] }}%. @if($vendor['average_preparation_minutes'] !== null) Average preparation {{ $vendor['average_preparation_minutes'] }} minutes.@endif</p>
                        </div>
                        <a href="{{ $vendor['link'] }}" class="btn btn-outline-secondary btn-sm">Review</a>
                    </div>
                @endforeach
            </div>
            <div class="ic-small ic-muted mt-2">Analytical signals do not suspend vendors automatically. Review operational records before taking action.</div>
        </div>
    </div>
    @endif

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Vendor performance ranking</h3><span class="meta">Orders created in the selected period</span></div>
        <div class="ic-card-body ic-scroll">
            <table class="ic-table">
                <thead><tr><th>Vendor</th><th>Status</th><th>Orders</th><th>Acceptance</th><th>Completion</th><th>Cancellations</th><th>Avg preparation</th></tr></thead>
                <tbody>
                @forelse($data['rankings'] as $row)
                    <tr>
                        <td><a href="{{ $row['link'] }}">{{ $row['name'] }}</a></td>
                        <td><span class="ic-badge {{ $row['status'] === 'active' ? 'good' : 'warn' }}">{{ $row['status'] ?: 'unknown' }}</span></td>
                        <td>{{ number_format($row['total_orders']) }}</td>
                        <td>{{ $row['acceptance_rate'] }}%</td>
                        <td>{{ $row['completion_rate'] }}%</td>
                        <td>{{ $row['cancellation_rate'] }}%</td>
                        <td>{{ $row['average_preparation_minutes'] === null ? 'N/A' : $row['average_preparation_minutes'].' min' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ic-muted">No vendor order records in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
