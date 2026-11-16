@extends('agent.layouts.agent')

@section('title', 'Leads & Prospects')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">Leads & Prospects</h2>
            <p class="jt-muted mb-0">Track, follow up and convert leads.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('agent.leads.create') }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Add Lead</a>
            <a href="{{ route('agent.leads.followups') }}" class="btn btn-sm btn-outline-dark">Follow-ups</a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-3 mb-3">
    @foreach ([
        ['Total', $metrics['total'], 'fa-users'],
        ['New', $metrics['new'], 'fa-user-plus'],
        ['Contacted', $metrics['contacted'], 'fa-phone'],
        ['Interested', $metrics['interested'], 'fa-star'],
        ['Follow-up', $metrics['follow_up'], 'fa-calendar'],
        ['Converted', $metrics['converted'], 'fa-check'],
        ['Lost', $metrics['lost'], 'fa-xmark'],
        ['Overdue', $metrics['overdue'], 'fa-triangle-exclamation'],
        ['Conversion', $metrics['conversion_rate'].'%', 'fa-chart-line'],
    ] as $card)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="jt-card p-3 h-100">
                <div class="jt-muted" style="font-size:12px;">{{ $card[0] }}</div>
                <div class="fw-bold" style="font-size:22px;">{{ $card[1] }}</div>
                <i class="fas {{ $card[2] }} jt-muted"></i>
            </div>
        </div>
    @endforeach
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <form method="GET" action="{{ route('agent.leads.index') }}" class="row g-2">
        <div class="col-md-3">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, phone or email">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach (\App\Models\SalesLead::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="source" class="form-select">
                <option value="">All sources</option>
                @foreach (\App\Models\SalesLead::SOURCES as $key => $label)
                    <option value="{{ $key }}" @selected(request('source') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="region_id" class="form-select">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="location_id" class="form-select">
                <option value="">All locations</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" placeholder="From">
        </div>
        <div class="col-md-3">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" placeholder="To">
        </div>
        <div class="col-md-3">
            <input type="date" name="followup_from" value="{{ request('followup_from') }}" class="form-control" placeholder="Follow-up from">
        </div>
        <div class="col-md-3">
            <input type="date" name="followup_to" value="{{ request('followup_to') }}" class="form-control" placeholder="Follow-up to">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-sm btn-outline-dark">Filter</button>
            <a href="{{ route('agent.leads.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="jt-card p-0 mb-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Source</th>
                    <th>Interest</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Next follow-up</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td>{{ $lead->name }}</td>
                        <td>{{ $lead->phone }}</td>
                        <td>{{ \App\Models\SalesLead::SOURCES[$lead->source] ?? $lead->source }}</td>
                        <td>{{ $lead->interest ?: '—' }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$lead->status)) }}</span></td>
                        <td>{{ $lead->created_at?->format('d M Y') }}</td>
                        <td>{{ $lead->next_followup_at?->format('d M Y') ?: '—' }}</td>
                        <td><a href="{{ route('agent.leads.show', $lead) }}" class="btn btn-sm btn-outline-dark">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center jt-muted py-4">No leads found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $leads->links() }}</div>

<div class="jt-card p-3 p-md-4 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Pipeline (drag and drop)</h5>
        <a href="{{ route('agent.leads.analytics') }}" class="btn btn-sm btn-outline-dark">Analytics</a>
    </div>
    <div class="row g-2">
        @foreach ($pipeline as $status => $items)
            <div class="col-md-2">
                <div class="border rounded p-2 lead-column" data-status="{{ $status }}" style="min-height:180px;">
                    <div class="fw-bold mb-2">{{ ucfirst(str_replace('_',' ',$status)) }} ({{ $items->count() }})</div>
                    @foreach ($items as $lead)
                        <div class="border rounded p-2 mb-2 bg-white lead-card" draggable="true" data-lead-id="{{ $lead->id }}">
                            <div class="fw-semibold" style="font-size:13px;">{{ $lead->name }}</div>
                            <div class="jt-muted" style="font-size:11px;">{{ $lead->phone }}</div>
                            <a href="{{ route('agent.leads.show', $lead) }}" class="btn btn-sm btn-outline-dark mt-1">Open</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let draggedId = null;

    document.querySelectorAll('.lead-card').forEach(function (card) {
        card.addEventListener('dragstart', function () {
            draggedId = card.dataset.leadId;
            card.classList.add('opacity-50');
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('opacity-50');
        });
    });

    document.querySelectorAll('.lead-column').forEach(function (column) {
        column.addEventListener('dragover', function (e) {
            e.preventDefault();
            column.classList.add('bg-light');
        });
        column.addEventListener('dragleave', function () {
            column.classList.remove('bg-light');
        });
        column.addEventListener('drop', function (e) {
            e.preventDefault();
            column.classList.remove('bg-light');

            if (!draggedId) return;

            const status = column.dataset.status;
            let lostReason = null;

            if (status === 'lost') {
                lostReason = window.prompt('Reason for marking this lead as lost:\nnot_interested, cannot_afford, unreachable, purchased_elsewhere, invalid_contact, other');
                if (lostReason === null) return;
            }

            const body = new FormData();
            body.append('_token', csrf);
            body.append('status', status);
            if (lostReason) body.append('lost_reason', lostReason);

            fetch('/agent/leads/' + draggedId + '/status', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function (response) {
                if (response.ok) {
                    window.location.reload();
                } else {
                    alert('Unable to move lead.');
                }
            });
        });
    });
})();
</script>
@endsection
