@extends('admin.layouts.admin')
@section('title', 'Customer Intelligence')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'Customer Intelligence',
        'subtitle' => 'Customer growth, repeat behaviour, support history and geographic demand. Sensitive identity fields require customer access.',
    ])

    <div class="ic-grid k4">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Active customers', 'value' => number_format($data['active']), 'hint' => number_format($data['growth'], 1).'% vs previous period', 'tone' => $data['growth'] < 0 ? 'warn' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'New customers', 'value' => number_format($data['new']), 'hint' => 'Created in selected period'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Repeat rate', 'value' => $data['repeat_rate'].'%', 'hint' => number_format($data['repeat']).' repeat customers'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Open disputes', 'value' => number_format($data['open_disputes']), 'hint' => number_format($data['open_tickets']).' open support tickets', 'tone' => $data['open_disputes'] > 0 ? 'warn' : 'good'])
    </div>

    <div class="ic-grid k2 mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Top customers by order count</h3><span class="meta">Identity shown only with customer access</span></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Customer</th><th>Orders</th><th>Last order</th></tr></thead>
                    <tbody>
                    @forelse($data['top_customers'] as $row)
                        <tr>
                            <td>@if($row['link'])<a href="{{ $row['link'] }}">{{ $row['name'] }}</a>@else{{ $row['name'] }}@endif</td>
                            <td>{{ number_format($row['orders']) }}</td>
                            <td>{{ $row['last_order_at'] ? \Illuminate\Support\Carbon::parse($row['last_order_at'])->format('d M Y') : 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ic-muted">No customer orders in this period.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Support pressure</h3><a class="meta" href="{{ route('admin.support-tickets.index') }}">Support tickets</a></div>
            <div class="ic-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Tickets in period</span><strong>{{ number_format($data['complaints']) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Open tickets</span><strong>{{ number_format($data['open_tickets']) }}</strong></div>
                <div class="d-flex justify-content-between"><span class="ic-muted">Open disputes</span><strong>{{ number_format($data['open_disputes']) }}</strong></div>
                <div class="ic-small ic-muted mt-3">Complaint counts use ticket category records. Customer satisfaction is not shown because no complete feedback dataset is currently available.</div>
            </div>
        </div>
    </div>

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Customer demand by region</h3><a class="meta" href="{{ route('admin.command-center.geography') }}">Geography</a></div>
        <div class="ic-card-body ic-scroll">
            <table class="ic-table">
                <thead><tr><th>Region</th><th>Active customer orders</th><th>Delivered</th><th>Failure rate</th></tr></thead>
                <tbody>
                @forelse($data['geography'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['orders']) }}</td><td>{{ number_format($row['delivered']) }}</td><td>{{ $row['failure_rate'] }}%</td></tr>
                @empty
                    <tr><td colspan="4" class="ic-muted">No regional customer records.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
