@extends('logistics.layouts.app')

@section('title', 'Customer Support & Disputes')
@section('heading', 'Customer Support & Disputes')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Support</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Operations</span><h2>Support command center</h2><p>Real support tickets, disputes, conversations and escalation tracking.</p></div>
    <div class="page-actions"><button class="btn btn-juvo btn-sm" data-bs-toggle="modal" data-bs-target="#createTicket">New ticket</button></div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Open', $kpis['open'], ''],
        ['Unassigned', $kpis['unassigned'], 'text-warning'],
        ['In progress', $kpis['in_progress'], ''],
        ['High priority', $kpis['high_priority'], 'text-danger'],
        ['Escalated', $kpis['escalated'], 'text-danger'],
        ['Resolved', $kpis['resolved'], 'text-success'],
        ['Closed', $kpis['closed'], 'text-muted'],
        ['Overdue', $kpis['overdue'], 'text-danger'],
    ] as $card)
        <div class="col-md-3 col-sm-6"><div class="stat-card"><div class="stat-label">{{ $card[0] }}</div><div class="stat-value {{ $card[2] }}">{{ $card[1] }}</div></div></div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label">Search</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Ticket or subject"></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach($statuses ?? ['open','pending','resolved','closed'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="">All</option>@foreach($priorities ?? ['low','normal','high','urgent'] as $priority)<option value="{{ $priority }}" @selected(request('priority')===$priority)>{{ $priority }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select"><option value="">All</option>@foreach(['delivery','cod','payment','rider','vendor','location','other'] as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Dispute</label><select name="dispute_type" class="form-select"><option value="">All</option>@foreach(['failed_delivery','delayed_delivery','missing_package','damaged_package','wrong_confirmation','rider_misconduct','cod_discrepancy','refund','payment','delivery_fee','wrong_location','other'] as $type)<option value="{{ $type }}" @selected(request('dispute_type')===$type)>{{ $type }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-juvo w-100">Filter</button><a class="btn btn-outline-secondary" href="{{ route('logistics.support.index') }}">Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Requester</th><th>Order</th><th>Rider</th><th>Category</th><th>Dispute</th><th>Priority</th><th>Status</th><th>Assignee</th><th>Created</th><th></th></tr></thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr>
                        <td>{{ $ticket->ticket_number }}</td>
                        <td>{{ $ticket->subject }}</td>
                        <td>{{ $ticket->customer?->name ?? $ticket->vendor?->name ?? $ticket->rider?->full_name ?? $ticket->guest_name ?? 'Guest' }}</td>
                        <td>{{ $ticket->order_id ? '#'.$ticket->order_id : '—' }}</td>
                        <td>{{ $ticket->rider?->full_name ?? '—' }}</td>
                        <td>{{ $ticket->category }}</td>
                        <td>{{ $ticket->dispute_type ?: '—' }}</td>
                        <td>{{ $ticket->priority }}</td>
                        <td>{{ $ticket->status }}</td>
                        <td>{{ $ticket->logisticsAssignee?->name ?? 'Unassigned' }}</td>
                        <td>{{ optional($ticket->created_at)->format('d M Y H:i') }}</td>
                        <td class="text-end"><a href="{{ route('logistics.support.show', $ticket) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="empty-state">No support tickets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $tickets->links() }}</div>
</div>

<div class="modal fade" id="createTicket" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('logistics.support.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Create support ticket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Subject</label><input name="subject" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Priority</label><select name="priority" class="form-select" required><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
                    <div class="col-md-3"><label class="form-label">Category</label><select name="category" class="form-select"><option value="delivery">Delivery</option><option value="cod">COD</option><option value="payment">Payment</option><option value="rider">Rider</option><option value="vendor">Vendor</option><option value="location">Location</option><option value="other">Other</option></select></div>
                    <div class="col-md-4"><label class="form-label">Dispute type</label><select name="dispute_type" class="form-select"><option value="">None</option>@foreach(['failed_delivery','delayed_delivery','missing_package','damaged_package','wrong_confirmation','rider_misconduct','cod_discrepancy','refund','payment','delivery_fee','wrong_location','other'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Customer ID</label><input type="number" name="customer_id" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Vendor ID</label><input type="number" name="vendor_id" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Rider ID</label><input type="number" name="rider_id" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Order ID</label><input type="number" name="order_id" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Product ID</label><input type="number" name="product_id" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="4" required></textarea></div>
                    <div class="col-12 form-check ms-2"><input type="checkbox" name="is_internal" value="1" class="form-check-input" id="internalCreate"><label class="form-check-label" for="internalCreate">Internal note only</label></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-juvo">Create</button></div>
        </form>
    </div>
</div>
@endsection
