@extends('agent.layouts.agent')

@section('title', 'Lead Profile')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">{{ $lead->name }}</h2>
            <p class="jt-muted mb-0">{{ $lead->phone }} · {{ $lead->email }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('agent.leads.edit', $lead) }}" class="btn btn-sm btn-outline-dark">Edit</a>
            @if ($lead->status !== 'converted')
                <form method="POST" action="{{ route('agent.leads.convert', $lead) }}">
                    @csrf
                    <button class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Convert to Customer</button>
                </form>
            @endif
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
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Contact Information</h5>
            <div><strong>Region:</strong> {{ $lead->region?->name ?: '—' }}</div>
            <div><strong>Location:</strong> {{ $lead->location?->name ?: '—' }}</div>
            <div><strong>Source:</strong> {{ \App\Models\SalesLead::SOURCES[$lead->source] ?? $lead->source }}</div>
            <div><strong>Status:</strong> {{ ucfirst(str_replace('_',' ',$lead->status)) }}</div>
            <div><strong>Added:</strong> {{ $lead->created_at?->format('d M Y') }}</div>
            <div><strong>Last contact:</strong> {{ $lead->last_contacted_at?->format('d M Y H:i') ?: '—' }}</div>
            <div><strong>Next follow-up:</strong> {{ $lead->next_followup_at?->format('d M Y H:i') ?: '—' }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Interest and Requirements</h5>
            <div><strong>Interest:</strong> {{ $lead->interest ?: '—' }}</div>
            <div><strong>Category:</strong> {{ $lead->category?->name ?: '—' }}</div>
            <div><strong>Product:</strong> {{ $lead->product?->translation?->name ?: '—' }}</div>
            <div><strong>Budget:</strong> {{ $lead->budget ? number_format((float)$lead->budget,2) : '—' }}</div>
            <div><strong>Timeline:</strong> {{ $lead->timeline ?: '—' }}</div>
            <div><strong>Needs:</strong> {{ $lead->needs ?: '—' }}</div>
            @if ($lead->lost_reason)
                <div><strong>Lost reason:</strong> {{ \App\Models\SalesLead::LOST_REASONS[$lead->lost_reason] ?? $lead->lost_reason }}</div>
            @endif
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Status</h5>
    <form method="POST" action="{{ route('agent.leads.status', $lead) }}" class="row g-2">
        @csrf
        <div class="col-md-4">
            <select name="status" class="form-select">
                @foreach (['new','contacted','interested','follow_up','converted','lost','reopen'] as $status)
                    <option value="{{ $status }}">{{ ucfirst(str_replace('_',' ',$status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <select name="lost_reason" class="form-select">
                <option value="">Lost reason (if lost)</option>
                @foreach (\App\Models\SalesLead::LOST_REASONS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-outline-dark">Update Status</button>
        </div>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Notes</h5>
            <form method="POST" action="{{ route('agent.leads.notes.store', $lead) }}" class="mb-3">
                @csrf
                <textarea name="note" class="form-control" rows="2" required placeholder="Add a timestamped note"></textarea>
                <button class="btn btn-sm btn-primary mt-2" style="background:var(--jt-primary);border-color:var(--jt-primary);">Add Note</button>
            </form>
            @forelse ($lead->notes()->get() as $note)
                <div class="border rounded p-2 mb-2">
                    <div class="jt-muted" style="font-size:12px;">{{ $note->created_at?->format('d M Y H:i') }} · {{ $note->salesRep?->name }}</div>
                    <div>{{ $note->note }}</div>
                </div>
            @empty
                <div class="jt-muted">No notes yet.</div>
            @endforelse
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Follow-ups</h5>
            <form method="POST" action="{{ route('agent.leads.followups.store', $lead) }}" class="mb-3">
                @csrf
                <input type="text" name="reason" class="form-control mb-2" placeholder="Reason" required>
                <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Notes"></textarea>
                <input type="datetime-local" name="scheduled_at" class="form-control mb-2" required>
                <button class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Schedule Follow-up</button>
            </form>
            @forelse ($lead->followUps as $followUp)
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $followUp->reason }}</strong>
                        @if ($followUp->completed_at)
                            <span class="badge bg-success">Completed</span>
                        @elseif ($followUp->isOverdue())
                            <span class="badge bg-danger">Overdue</span>
                        @else
                            <span class="badge bg-warning text-dark">Pending</span>
                        @endif
                    </div>
                    <div class="jt-muted" style="font-size:12px;">{{ $followUp->scheduled_at?->format('d M Y H:i') }}</div>
                    @if ($followUp->notes)<div>{{ $followUp->notes }}</div>@endif
                    @if (! $followUp->completed_at)
                        <form method="POST" action="{{ route('agent.leads.followups.complete', $followUp) }}" class="mt-2">
                            @csrf
                            <button class="btn btn-sm btn-outline-success">Mark Complete</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="jt-muted">No follow-ups yet.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4">
    <h5 class="mb-2">Activity Timeline</h5>
    @forelse ($lead->statusHistories as $history)
        <div class="mb-2">
            <span class="jt-muted" style="font-size:12px;">{{ $history->created_at?->format('d M Y H:i') }} · {{ $history->salesRep?->name }}</span>
            <div>{{ ucfirst(str_replace('_',' ',$history->from_status ?: '—')) }} → {{ ucfirst(str_replace('_',' ',$history->to_status)) }} @if($history->reason) ({{ $history->reason }}) @endif</div>
        </div>
    @empty
        <div class="jt-muted">No activity yet.</div>
    @endforelse
    @foreach ($lead->notes as $note)
        <div class="mb-2">
            <span class="jt-muted" style="font-size:12px;">{{ $note->created_at?->format('d M Y H:i') }} · {{ $note->salesRep?->name }}</span>
            <div>Note: {{ $note->note }}</div>
        </div>
    @endforeach
</div>
@endsection
