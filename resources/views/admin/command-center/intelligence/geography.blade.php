@extends('admin.layouts.admin')
@section('title', 'Geographical Intelligence')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'Geographical Intelligence',
        'subtitle' => 'Demand, delivery outcomes and rider coverage using the shared Juvo region and location hierarchy.',
    ])

    <div class="ic-grid k2">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Regional performance</h3><span class="meta">Orders linked to shipping addresses</span></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Region</th><th>Orders</th><th>Delivered</th><th>Failed</th><th>Failure rate</th></tr></thead>
                    <tbody>
                    @forelse($data['regions'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['orders']) }}</td><td>{{ number_format($row['delivered']) }}</td><td>{{ number_format($row['failed']) }}</td><td>{{ $row['failure_rate'] }}%</td></tr>
                    @empty
                        <tr><td colspan="5" class="ic-muted">No regional order records.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Rider distribution</h3><span class="meta">Active riders by registered state</span></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Area</th><th>Active riders</th><th>Online</th></tr></thead>
                    <tbody>
                    @forelse($data['rider_distribution'] as $row)
                        <tr><td>{{ $row->state ?: 'Unassigned' }}</td><td>{{ number_format($row->riders) }}</td><td>{{ number_format($row->online) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="ic-muted">No rider location records.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Location performance</h3><span class="meta">Demand and delivery outcomes by shared location</span></div>
        <div class="ic-card-body ic-scroll">
            <table class="ic-table">
                <thead><tr><th>Location</th><th>Region</th><th>Orders</th><th>Delivered</th><th>Failed / returned</th><th>Failure rate</th></tr></thead>
                <tbody>
                @forelse($data['locations'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['region'] }}</td><td>{{ number_format($row['total']) }}</td><td>{{ number_format($row['delivered']) }}</td><td>{{ number_format($row['failed']) }}</td><td>{{ $row['failure_rate'] }}%</td></tr>
                @empty
                    <tr><td colspan="6" class="ic-muted">No location order records.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Vendor distribution</h3><span class="meta">Active vendor addresses</span></div>
        <div class="ic-card-body">
            <div class="ic-empty">Interactive maps are not enabled because the current region and location records do not include verified geographic coordinates. The shared hierarchy is used for traceable tabular analysis.</div>
        </div>
    </div>
</div>
@endsection
