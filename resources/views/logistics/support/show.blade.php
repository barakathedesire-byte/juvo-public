@extends('logistics.layouts.app')

@section('title', 'Support Ticket')
@section('heading', 'Support Ticket')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Support</span><span aria-hidden="true"> / </span><span>{{ $ticket->ticket_number }}</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Support</span><h2>{{ $ticket->ticket_number }} — {{ $ticket->subject }}</h2><p>{{ $ticket->category }} · {{ $ticket->priority }} · {{ $ticket->status }} · SLA: {{ $slaState }}</p></div>
    <div class="page-actions"><a href="{{ route('logistics.support.index') }}" class="btn btn-outline-secondary btn-sm">Back</a></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Conversation</h6></div>
            <div class="card-body" style="max-height:520px;overflow:auto;">
                @forelse($ticket->messages as $message)
                    <div class="mb-3 p-3 rounded-3 {{ $message->is_internal ? 'bg-light border' : 'border' }}">
                        <div class="d-flex justify-content-between mb-1">
                            <strong>{{ ucfirst($message->sender_type) }}{{ $message->is_internal ? ' (internal note)' : '' }}</strong>
                            <span class="text-muted small">{{ optional($message->created_at)->format('d M Y H:i') }}</span>
                        </div>
                        <div style="white-space:pre-wrap;">{{ $message->body }}</div>
                        @if($message->attachments)
                            <div class="mt-2 d-flex gap-2 flex-wrap">
                                @foreach($message->attachments as $path)
                                    <a href="{{ asset('storage/'.$path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Attachment</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">No messages yet.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('logistics.support.reply', $ticket) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-2"><label class="form-label">Reply</label><textarea name="body" class="form-control" rows="4" required maxlength="5000"></textarea></div>
                    <div class="mb-2"><label class="form-label">Attachments</label><input type="file" name="attachments[]" class="form-control" multiple></div>
                    <div class="form-check mb-2"><input type="checkbox" name="is_internal" value="1" class="form-check-input" id="internalReply"><label class="form-check-label" for="internalReply">Internal note</label></div>
                    <button class="btn btn-juvo">Send reply</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Ticket details</h6></div>
            <div class="card-body">
                <div class="mb-2"><strong>Requester:</strong> {{ $ticket->customer?->name ?? $ticket->vendor?->name ?? $ticket->rider?->full_name ?? $ticket->guest_name ?? 'Guest' }}</div>
                <div class="mb-2"><strong>Email:</strong> {{ $ticket->customer?->email ?? $ticket->guest_email ?? '—' }}</div>
                <div class="mb-2"><strong>Category:</strong> {{ $ticket->category }}</div>
                <div class="mb-2"><strong>Dispute:</strong> {{ $ticket->dispute_type ?: '—' }}</div>
                <div class="mb-2"><strong>Created:</strong> {{ optional($ticket->created_at)->format('d M Y H:i') }}</div>
                <div class="mb-2"><strong>First response due:</strong> {{ optional($ticket->first_response_due_at)->format('d M Y H:i') ?: '—' }}</div>
                <div class="mb-2"><strong>Resolution due:</strong> {{ optional($ticket->resolution_due_at)->format('d M Y H:i') ?: '—' }}</div>
                <div class="mb-2"><strong>Escalation:</strong> level {{ $ticket->escalation_level ?? 0 }} @if($ticket->escalated_at) ({{ optional($ticket->escalated_at)->format('d M Y H:i') }}) @endif</div>
                @if($ticket->resolution_category)<div class="mb-2"><strong>Resolution:</strong> {{ $ticket->resolution_category }}</div>@endif
                @if($ticket->resolution_notes)<div class="mb-2"><strong>Notes:</strong> {{ $ticket->resolution_notes }}</div>@endif
            </div>
        </div>

        @if(auth('logistics_admin')->user()?->canAccessLogisticsModule('finance') && $ticket->order)
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Order & financial context</h6></div>
                <div class="card-body">
                    <div class="mb-1"><strong>Order:</strong> #{{ $ticket->order->id }} ({{ $ticket->order->logistics_status }})</div>
                    <div class="mb-1"><strong>Delivery fee:</strong> {{ number_format((float)$ticket->order->delivery_fee,2) }}</div>
                    <div class="mb-1"><strong>Total:</strong> {{ number_format((float)$ticket->order->total_amount,2) }}</div>
                    @if($ticket->order->cod_collected_at)
                        <div class="mb-1"><strong>COD:</strong> {{ number_format((float)$ticket->order->cod_amount,2) }} collected; remitted {{ number_format((float)$ticket->order->cod_remitted_amount,2) }}; outstanding {{ number_format($ticket->order->codOutstanding(),2) }}</div>
                    @endif
                    <div class="mb-1"><strong>Address:</strong> {{ $ticket->order->shippingAddress?->address }}, {{ $ticket->order->shippingAddress?->city }}, {{ $ticket->order->shippingAddress?->state }}</div>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Update ticket</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('logistics.support.update', $ticket) }}">
                    @csrf @method('PUT')
                    <div class="mb-2"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['open','pending','resolved','closed'] as $status)<option value="{{ $status }}" @selected($ticket->status===$status)>{{ $status }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">Priority</label><select name="priority" class="form-select">@foreach(['low','normal','high','urgent'] as $priority)<option value="{{ $priority }}" @selected($ticket->priority===$priority)>{{ $priority }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">Resolution category</label><input name="resolution_category" class="form-control" value="{{ $ticket->resolution_category }}"></div>
                    <div class="mb-2"><label class="form-label">Resolution notes</label><textarea name="resolution_notes" class="form-control" rows="3">{{ $ticket->resolution_notes }}</textarea></div>
                    <button class="btn btn-juvo w-100">Update</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Assignment</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('logistics.support.assign', $ticket) }}">
                    @csrf
                    <select name="assigned_logistics_admin_id" class="form-select mb-2">
                        <option value="">Unassigned</option>
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}" @selected($ticket->assigned_logistics_admin_id===$admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-secondary w-100">Assign</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Escalate</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('logistics.support.escalate', $ticket) }}">
                    @csrf
                    <textarea name="reason" class="form-control mb-2" rows="2" placeholder="Escalation reason"></textarea>
                    <button class="btn btn-outline-danger w-100">Escalate</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
