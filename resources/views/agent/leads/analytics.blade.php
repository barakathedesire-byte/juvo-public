@extends('agent.layouts.agent')

@section('title', 'Lead Analytics')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="jt-section-title mb-0">Lead Analytics</h2>
        <a href="{{ route('agent.leads.index') }}" class="btn btn-sm btn-outline-secondary">Back to Leads</a>
    </div>
</div>

<form method="GET" action="{{ route('agent.leads.analytics') }}" class="jt-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-3">
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
        </div>
        <div class="col-md-3">
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-outline-dark">Apply</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    @foreach ([
        ['Total leads', $metrics['total']],
        ['Converted', $metrics['converted']],
        ['Conversion rate', $metrics['conversion_rate'].'%'],
        ['Avg. days to convert', $metrics['avg_conversion_days']],
        ['Follow-up completion', $metrics['followup_completion'].'%'],
    ] as $card)
        <div class="col-6 col-md-4">
            <div class="jt-card p-3 h-100">
                <div class="jt-muted" style="font-size:12px;">{{ $card[0] }}</div>
                <div class="fw-bold" style="font-size:22px;">{{ $card[1] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Conversion by Source</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Source</th><th>Total</th><th>Converted</th><th>Rate</th></tr></thead>
                <tbody>
                    @foreach ($bySource as $source => $data)
                        <tr>
                            <td><a href="{{ route('agent.leads.index', ['source' => $source]) }}">{{ \App\Models\SalesLead::SOURCES[$source] ?? $source }}</a></td>
                            <td>{{ $data['total'] }}</td>
                            <td>{{ $data['converted'] }}</td>
                            <td>{{ $data['rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Conversion by Location</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Location</th><th>Total</th><th>Converted</th><th>Rate</th></tr></thead>
                <tbody>
                    @foreach ($byLocation as $location => $data)
                        <tr>
                            <td>{{ $location }}</td>
                            <td>{{ $data['total'] }}</td>
                            <td>{{ $data['converted'] }}</td>
                            <td>{{ $data['rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Leads by Day</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Leads</th></tr></thead>
                <tbody>
                    @foreach ($byDay as $date => $count)
                        <tr><td>{{ $date }}</td><td>{{ $count }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-4">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Leads by Week</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Week</th><th>Leads</th></tr></thead>
                <tbody>
                    @foreach ($byWeek as $week => $count)
                        <tr><td>{{ $week }}</td><td>{{ $count }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-4">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Leads by Month</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Month</th><th>Leads</th></tr></thead>
                <tbody>
                    @foreach ($byMonth as $month => $count)
                        <tr><td>{{ $month }}</td><td>{{ $count }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4">
    <h5 class="mb-2">Lost Lead Reasons</h5>
    <table class="table table-sm mb-0">
        <thead><tr><th>Reason</th><th>Count</th></tr></thead>
        <tbody>
            @forelse ($lostReasons as $reason => $count)
                <tr>
                    <td>{{ \App\Models\SalesLead::LOST_REASONS[$reason] ?? ($reason ?: 'Unspecified') }}</td>
                    <td>{{ $count }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="jt-muted">No lost leads in this range.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
