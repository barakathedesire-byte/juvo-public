@extends('agent.layouts.agent')

@section('title', 'Lead Follow-ups')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="jt-section-title mb-0">Lead Follow-ups</h2>
        <div class="btn-group">
            @foreach (['upcoming','today','overdue','completed'] as $filter)
                <a href="{{ route('agent.leads.followups', ['filter' => $filter]) }}" class="btn btn-sm {{ request('filter','upcoming') === $filter ? 'btn-primary' : 'btn-outline-secondary' }}" style="{{ request('filter','upcoming') === $filter ? 'background:var(--jt-primary);border-color:var(--jt-primary);' : '' }}">{{ ucfirst($filter) }}</a>
            @endforeach
        </div>
    </div>
</div>

<div class="jt-card p-0">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Lead</th><th>Reason</th><th>Scheduled</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($followUps as $followUp)
                    <tr>
                        <td><a href="{{ route('agent.leads.show', $followUp->lead) }}">{{ $followUp->lead?->name }}</a></td>
                        <td>{{ $followUp->reason }}</td>
                        <td>{{ $followUp->scheduled_at?->format('d M Y H:i') }}</td>
                        <td>
                            @if ($followUp->completed_at)
                                <span class="badge bg-success">Completed</span>
                            @elseif ($followUp->isOverdue())
                                <span class="badge bg-danger">Overdue</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if (! $followUp->completed_at)
                                <form method="POST" action="{{ route('agent.leads.followups.complete', $followUp) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">Complete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center jt-muted py-4">No follow-ups found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $followUps->links() }}</div>
@endsection
