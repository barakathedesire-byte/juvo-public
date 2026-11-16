@extends('admin.layouts.admin')
@section('title', 'Intelligence Command Center')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'Executive Overview',
        'subtitle' => 'A single, traceable view of marketplace demand, fulfillment, delivery operations, customers and system health.',
    ])

    @php
        $m = $overview['marketplace'];
        $l = $overview['logistics'];
        $v = $overview['vendors'];
        $p = $overview['products'];
        $c = $overview['customers'];
        $r = $overview['risks'];
        $h = $overview['health'];
    @endphp

    <div class="ic-grid k4">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Orders', 'value' => number_format($m['orders']), 'hint' => number_format($m['growth'], 1).'% vs previous period', 'tone' => $m['growth'] < 0 ? 'warn' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Delivered', 'value' => number_format($l['completed']), 'hint' => $l['success_rate'].'% delivery success', 'tone' => $l['success_rate'] >= 80 ? 'good' : 'warn'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Active vendors', 'value' => number_format($v['active']), 'hint' => $v['pending'].' pending approval', 'tone' => $v['pending'] > 0 ? 'warn' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Open risks', 'value' => number_format($r['items']->count()), 'hint' => $r['critical_cases'] !== null ? $r['critical_cases'].' critical risk cases' : 'Rule-based findings only', 'tone' => $r['items']->where('severity', 'critical')->count() > 0 ? 'bad' : ($r['items']->count() ? 'warn' : 'good')])
    </div>

    <div class="ic-grid k4 mt-3">
        @include('admin.command-center.intelligence._kpi', ['label' => 'Awaiting vendor action', 'value' => number_format($m['awaiting_vendor']), 'hint' => number_format($m['preparing']).' being prepared'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Pending dispatch', 'value' => number_format($l['pending_dispatch']), 'hint' => number_format($l['delayed']).' delayed deliveries', 'tone' => $l['delayed'] > 0 ? 'warn' : 'good'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Rider utilisation', 'value' => $l['rider_utilization'].'%', 'hint' => $l['busy_riders'].' of '.$l['active_riders'].' active riders busy'])
        @include('admin.command-center.intelligence._kpi', ['label' => 'Open support', 'value' => number_format($c['open_tickets']), 'hint' => $c['open_disputes'].' open disputes', 'tone' => $c['open_disputes'] > 0 ? 'warn' : 'good'])
    </div>

    <div class="ic-split mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Order volume trend</h3><span class="meta">Daily order creation</span></div>
            <div class="ic-card-body">
                @include('admin.command-center.intelligence._trend', ['trend' => $m['trend']])
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Order status distribution</h3><a class="meta" href="{{ route('admin.command-center.marketplace') }}">Investigate</a></div>
            <div class="ic-card-body">
                @forelse ($m['status_distribution'] as $row)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between ic-small"><span>{{ $row['label'] }}</span><strong>{{ number_format($row['count']) }}</strong></div>
                        <div class="ic-bar mt-1"><span style="width:{{ min(100, $row['percentage']) }}%"></span></div>
                    </div>
                @empty
                    <div class="ic-empty">No orders in this period.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="ic-grid k3 mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Marketplace</h3><a class="meta" href="{{ route('admin.command-center.marketplace') }}">Open</a></div>
            <div class="ic-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Average order value</span><strong>{{ number_format($m['average_order_value'], 2) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Cancellation rate</span><strong>{{ $m['cancellation_rate'] }}%</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">New customers</span><strong>{{ number_format($c['new']) }}</strong></div>
                <div class="d-flex justify-content-between"><span class="ic-muted">Repeat customers</span><strong>{{ $c['repeat_rate'] }}%</strong></div>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Logistics</h3><a class="meta" href="{{ route('admin.command-center.logistics') }}">Open</a></div>
            <div class="ic-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Avg delivery duration</span><strong>{{ $l['average_duration_minutes'] === null ? 'Not available' : $l['average_duration_minutes'].' min' }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Dispatch acceptance</span><strong>{{ $l['dispatch_acceptance_rate'] }}%</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Online riders</span><strong>{{ $l['online_riders'] }}</strong></div>
                <div class="d-flex justify-content-between"><span class="ic-muted">Failed or returned</span><strong>{{ number_format($l['failed']) }}</strong></div>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Inventory exceptions</h3><a class="meta" href="{{ route('admin.command-center.marketplace') }}">Open</a></div>
            <div class="ic-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Out of stock</span><strong class="text-danger">{{ number_format($p['out_of_stock']) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Low stock (≤ {{ $p['low_stock_threshold'] }})</span><strong>{{ number_format($p['low_stock']) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Pending product approval</span><strong>{{ number_format($p['pending_approval']) }}</strong></div>
                <div class="d-flex justify-content-between"><span class="ic-muted">Declining products</span><strong>{{ $p['declining']->count() }}</strong></div>
            </div>
        </div>
    </div>

    <div class="ic-split mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Executive insights</h3><span class="meta">Rule-based, evidence-backed</span></div>
            <div class="ic-card-body">
                <div class="ic-list">
                    @forelse ($overview['insights'] as $insight)
                        <div class="ic-list-row ic-insight {{ $insight['severity'] }}">
                            <div class="body">
                                <strong>{{ $insight['title'] }}</strong>
                                <p>{{ $insight['evidence'] }}</p>
                                <p class="mt-1"><a href="{{ $insight['link'] }}" class="ic-small">Review supporting data</a></p>
                            </div>
                            <span class="ic-badge {{ $insight['severity'] === 'critical' ? 'bad' : ($insight['severity'] === 'warning' ? 'warn' : 'info') }}">{{ $insight['severity'] }}</span>
                        </div>
                    @empty
                        <div class="ic-empty">No material anomalies detected from the available records.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>System health</h3>@if($canHealth)<a class="meta" href="{{ route('admin.command-center.health') }}">Details</a>@endif</div>
            <div class="ic-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="ic-muted">Overall signal</span>
                    <span class="ic-badge {{ $h['status'] === 'healthy' ? 'good' : ($h['status'] === 'degraded' ? 'warn' : 'bad') }}">{{ $h['status'] }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Database</span><span>{{ $h['database']['status'] }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Failed integrations</span><span>{{ $h['integrations']['failed'] }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="ic-muted">Pending jobs</span><span>{{ $h['jobs']['pending'] }}</span></div>
                <div class="d-flex justify-content-between"><span class="ic-muted">Failed jobs</span><span>{{ $h['jobs']['failed'] }}</span></div>
                <div class="ic-small ic-muted mt-3">Checked {{ $h['checked_at']->format('d M Y H:i') }}. A loading dashboard is not treated as proof of health.</div>
            </div>
        </div>
    </div>

    <div class="ic-grid k2 mt-3">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Top regions by demand</h3><a class="meta" href="{{ route('admin.command-center.geography') }}">Geography</a></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Region</th><th>Orders</th><th>Delivered</th><th>Failure rate</th></tr></thead>
                    <tbody>
                    @forelse($overview['top_regions'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['orders']) }}</td><td>{{ number_format($row['delivered']) }}</td><td>{{ $row['failure_rate'] }}%</td></tr>
                    @empty
                        <tr><td colspan="4" class="ic-muted">No regional order data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Vendor performance</h3><a class="meta" href="{{ route('admin.command-center.vendors') }}">Vendors</a></div>
            <div class="ic-card-body ic-scroll">
                <table class="ic-table">
                    <thead><tr><th>Vendor</th><th>Orders</th><th>Completed</th><th>Canceled</th></tr></thead>
                    <tbody>
                    @forelse($overview['top_vendors'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['total_orders']) }}</td><td>{{ $row['completion_rate'] }}%</td><td>{{ $row['cancellation_rate'] }}%</td></tr>
                    @empty
                        <tr><td colspan="4" class="ic-muted">No vendor orders in this period.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>Ask Juvo Intelligence</h3><span class="meta">Answers use the selected period and real records</span></div>
        <div class="ic-card-body">
            <form class="ic-assistant" id="icAssistantForm" action="{{ route('admin.command-center.assistant') }}" method="POST">
                @csrf
                <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                <input name="question" id="icQuestion" placeholder="e.g. Which vendors have the highest cancellation rates?" autocomplete="off" required maxlength="500">
                <button class="btn btn-primary btn-sm" type="submit">Ask</button>
            </form>
            <div class="d-flex gap-2 flex-wrap mt-2">
                @foreach(['Which vendors have the highest cancellation rates?', 'Which locations have the most delivery failures?', 'Which products are frequently out of stock?', 'Which delivery zones need more riders?'] as $prompt)
                    <button type="button" class="btn btn-outline-secondary btn-sm ic-suggest" data-question="{{ $prompt }}">{{ $prompt }}</button>
                @endforeach
            </div>
            <div id="icAssistantAnswer" class="ic-answer" style="display:none"></div>
        </div>
    </div>
</div>
@endsection
@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('icAssistantForm');
    const answer = document.getElementById('icAssistantAnswer');
    const input = document.getElementById('icQuestion');
    const esc = function (value) {
        const node = document.createElement('div');
        node.textContent = value === null || value === undefined ? '' : String(value);
        return node.innerHTML;
    };
    document.querySelectorAll('.ic-suggest').forEach(function (button) {
        button.addEventListener('click', function () { input.value = button.dataset.question; form.requestSubmit(); });
    });
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            answer.style.display = 'block';
            answer.innerHTML = '<span class="ic-muted">Checking available records…</span>';
            fetch(form.action, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
                body: new FormData(form)
            }).then(function (response) {
                if (!response.ok) throw new Error('Request failed');
                return response.json();
            }).then(function (data) {
                let html = '<strong>' + esc(data.answer) + '</strong><ul>';
                (data.bullets || []).forEach(function (item) { html += '<li>' + esc(item) + '</li>'; });
                html += '</ul><div class="ic-small ic-muted">' + esc(data.method) + '<br>Period: ' + esc(data.period) + '</div>';
                if (data.link) html += '<div class="mt-2"><a href="' + esc(data.link) + '">Open supporting data</a></div>';
                answer.innerHTML = html;
            }).catch(function () {
                answer.innerHTML = '<span class="text-danger">The assistant could not complete that request. Try a supported operational question.</span>';
            });
        });
    }
});
</script>
@endsection
