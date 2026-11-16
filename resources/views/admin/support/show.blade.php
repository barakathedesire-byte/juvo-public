@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ $ticket->ticket_number }} — {{ $ticket->subject }}</h6>
        <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-sm btn-light">Back</a>
    </div>

    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="d-flex flex-column gap-2 mb-3">
                    @foreach ($ticket->messages as $message)
                        <div class="border rounded-3 p-2 {{ $message->is_internal ? 'bg-light' : '' }}">
                            <div class="d-flex justify-content-between">
                                <strong class="text-capitalize">{{ $message->sender_type }}{{ $message->is_internal ? ' (internal note)' : '' }}</strong>
                                <span class="text-muted" style="font-size:12px;">{{ $message->created_at?->format('d M Y H:i') }}</span>
                            </div>
                            <div style="white-space:pre-wrap; font-size:14px;">{{ $message->body }}</div>
                        </div>
                    @endforeach
                </div>

                @can('support.reply')
                <form action="{{ route('admin.support-tickets.reply', $ticket->id) }}" method="POST">
                    @csrf
                    @if ($canned->isNotEmpty())
                        <div class="mb-2">
                            <select id="canned-selector" class="form-select form-select-sm">
                                <option value="">Insert canned response…</option>
                                @foreach ($canned as $response)
                                    <option value="{{ $response->id }}" data-body="{{ $response->body }}">{{ $response->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="mb-2">
                        <textarea id="reply-body" name="body" rows="4" class="form-control" placeholder="Write a reply..." required maxlength="5000"></textarea>
                        @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="is_internal">
                        <label class="form-check-label" for="is_internal">Internal note (not visible to customer)</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Send reply</button>
                </form>
                @endcan
            </div>

            <div class="col-lg-4">
                <div class="border rounded-3 p-3 mb-3">
                    <div class="mb-2"><strong>Requester:</strong> {{ $ticket->customer->name ?? ($ticket->guest_name ?: 'Guest') }}</div>
                    <div class="mb-2"><strong>Email:</strong> {{ $ticket->customer->email ?? ($ticket->guest_email ?: '—') }}</div>
                    <div class="mb-2"><strong>Category:</strong> {{ $ticket->category }}</div>
                    <div class="mb-2"><strong>Created:</strong> {{ $ticket->created_at?->format('d M Y H:i') }}</div>
                    @if ($ticket->assignee)
                        <div class="mb-2"><strong>Assignee:</strong> {{ $ticket->assignee->name }}</div>
                    @endif
                </div>

                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>SLA</strong>
                        @php
                            $slaClasses = ['met' => 'success', 'on_track' => 'secondary', 'due' => 'warning', 'breached' => 'danger'];
                        @endphp
                        <span class="badge bg-{{ $slaClasses[$slaState] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $slaState)) }}</span>
                    </div>
                    <div style="font-size:13px;">
                        <div>First response due: {{ $ticket->first_response_due_at?->format('d M Y H:i') ?? '—' }}</div>
                        <div>Resolution due: {{ $ticket->resolution_due_at?->format('d M Y H:i') ?? '—' }}</div>
                        @if ($ticket->first_responded_at)
                            <div>First responded: {{ $ticket->first_responded_at->format('d M Y H:i') }}</div>
                        @endif
                        @if ($ticket->escalated_at)
                            <div class="text-danger">Escalated L{{ $ticket->escalation_level }} on {{ $ticket->escalated_at->format('d M Y H:i') }}</div>
                        @endif
                        @if ($ticket->reopened_count > 0)
                            <div>Reopened {{ $ticket->reopened_count }}×</div>
                        @endif
                    </div>
                </div>

                @can('support.assign')
                <div class="border rounded-3 p-3 mb-3">
                    <form action="{{ route('admin.support-tickets.assign', $ticket->id) }}" method="POST">
                        @csrf
                        <label class="form-label">Assignee</label>
                        <select name="assigned_to" class="form-select form-select-sm mb-2">
                            <option value="">Unassigned</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected($ticket->assigned_to === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline-dark btn-sm">Assign</button>
                    </form>
                </div>
                @endcan

                <div class="border rounded-3 p-3 mb-3">
                    <form action="{{ route('admin.support-tickets.status', $ticket->id) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select form-select-sm">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ ucfirst($priority) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm">Update</button>
                    </form>
                </div>

                @can('support.escalate')
                <div class="border rounded-3 p-3">
                    <form action="{{ route('admin.support-tickets.escalate', $ticket->id) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Escalate</label>
                            <input type="text" name="reason" class="form-control form-control-sm" maxlength="500" placeholder="Reason (optional)">
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm">Escalate ticket</button>
                    </form>
                </div>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
$(document).ready(function () {
    var selector = document.getElementById('canned-selector');
    if (selector) {
        $(selector).on('change', function () {
            var body = $(this).find(':selected').data('body');
            if (body) {
                $('#reply-body').val(body);
            }
        });
    }
});
</script>
@endsection
