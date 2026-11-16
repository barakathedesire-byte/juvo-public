@extends('admin.layouts.admin')
@section('title', 'Operational Risk Intelligence')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'Operational Risk & Anomalies',
        'subtitle' => 'Evidence-backed findings from delivery, vendor, support and integration records. Findings are not accusations.',
    ])

    <div class="ic-grid k4">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Open risk cases', 'value' => $data['open_cases'] === null ? 'N/A' : number_format($data['open_cases']), 'hint' => $data['critical_cases'] === null ? 'Phase 3D table unavailable' : $data['critical_cases'].' critical', 'tone' => ($data['critical_cases'] ?? 0) > 0 ? 'bad' : 'warn'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Predictive alerts', 'value' => $data['open_alerts'] === null ? 'N/A' : number_format($data['open_alerts']), 'hint' => 'Open Phase 3C alerts'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Findings', 'value' => number_format($data['items']->count()), 'hint' => 'Rule-based, selected period'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Critical findings', 'value' => number_format($data['items']->where('severity', 'critical')->count()), 'tone' => $data['items']->where('severity', 'critical')->count() ? 'bad' : 'good'])
    </div>

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Detected issues</h3><span class="meta">Confirmed issue versus suspected anomaly is stated for each finding</span></div>
        <div class="ic-card-body">
            <div class="ic-list">
                @forelse($data['items'] as $item)
                    <div class="ic-list-row ic-insight {{ $item['severity'] }}">
                        <div class="body">
                            <strong>{{ $item['title'] }}</strong>
                            <p><strong>Where:</strong> {{ $item['where'] }} &middot; <strong>Evidence:</strong> {{ $item['evidence'] }}</p>
                            <p><strong>Confidence:</strong> {{ $item['confidence'] }} &middot; <strong>Suggested action:</strong> {{ $item['action'] }}</p>
                        </div>
                        <div class="text-end">
                            <span class="ic-badge {{ $item['severity'] === 'critical' ? 'bad' : 'warn' }}">{{ $item['severity'] }}</span>
                            <div class="mt-2"><a class="btn btn-outline-secondary btn-sm" href="{{ $item['link'] }}">Investigate</a></div>
                        </div>
                    </div>
                @empty
                    <div class="ic-empty">No operational findings met the evidence thresholds for this period.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Executive insights</h3><span class="meta">Marketplace and logistics patterns</span></div>
        <div class="ic-card-body">
            <div class="ic-list">
                @forelse($insights as $insight)
                    <div class="ic-list-row ic-insight {{ $insight['severity'] }}">
                        <div class="body">
                            <strong>{{ $insight['title'] }}</strong>
                            <p>{{ $insight['evidence'] }}</p>
                            <p>{{ $insight['action'] }}</p>
                        </div>
                        <a href="{{ $insight['link'] }}" class="btn btn-outline-secondary btn-sm">Open</a>
                    </div>
                @empty
                    <div class="ic-empty">No insights available for the selected data.</div>
                @endforelse
            </div>
            <div class="ic-small ic-muted mt-3">Rules never label a rider, vendor or customer as fraudulent. COD discrepancies are not evaluated here because this phase excludes finance reporting.</div>
        </div>
    </div>
</div>
@endsection
