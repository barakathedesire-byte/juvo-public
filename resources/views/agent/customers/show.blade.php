@extends('agent.layouts.agent')

@section('title', 'Customer Profile')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">{{ $customer->name }}</h2>
            <p class="jt-muted mb-0">{{ $customer->phone }} · {{ $customer->email }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('agent.customers.edit', $customer) }}" class="btn btn-sm btn-outline-dark">Edit</a>
            <a href="{{ route('agent.customers.place-order', $customer) }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Place Order</a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Personal Information</h5>
            <div><strong>Region:</strong> {{ $customer->region?->name ?: '—' }}</div>
            <div><strong>Location:</strong> {{ $customer->location?->name ?: '—' }}</div>
            <div><strong>Address:</strong> {{ $customer->address ?: '—' }}</div>
            <div><strong>Delivery address:</strong> {{ $customer->delivery_address ?: '—' }}</div>
            <div><strong>Status:</strong> {{ ucfirst(str_replace('_',' ',$customer->crm_status ?: 'new')) }}</div>
            <div><strong>Added:</strong> {{ $customer->created_at?->format('d M Y') }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Customer Insights</h5>
            <div><strong>Total orders:</strong> {{ $insights['total_orders'] }}</div>
            <div><strong>Total purchase value:</strong> {{ number_format($insights['total_value'], 2) }}</div>
            <div><strong>Last purchase:</strong> {{ $insights['last_order_date']?->format('d M Y') ?: '—' }}</div>
            <div><strong>Purchase frequency:</strong> {{ $insights['frequency'] }}</div>
            <div><strong>Segment:</strong> {{ ucfirst(str_replace('_',' ',$insights['segment'])) }}</div>
            <div><strong>Top categories:</strong>
                @forelse ($insights['top_categories'] as $categoryId => $count)
                    <span class="badge bg-secondary">Category {{ $categoryId }} ({{ $count }})</span>
                @empty
                    —
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Order History</h5>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Order</th><th>Date</th><th>Value</th><th>Payment</th><th>Delivery</th><th></th></tr></thead>
            <tbody>
                @forelse ($customer->orders as $order)
                    <tr>
                        <td>#{{ $order->id }}</td>
                        <td>{{ $order->created_at?->format('d M Y') }}</td>
                        <td>{{ number_format((float) $order->total_amount, 2) }}</td>
                        <td>{{ $order->payment_status ?: '—' }} ({{ $order->payment_method ?: '—' }})</td>
                        <td>{{ $order->status ?: '—' }}</td>
                        <td>
                            @if (Route::has('customer.orders.show'))
                                <a href="{{ route('customer.orders.show', $order) }}" class="btn btn-sm btn-outline-dark">View</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center jt-muted py-3">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="jt-card p-3 h-100">
            <h5 class="mb-2">Notes</h5>
            <form method="POST" action="{{ route('agent.customers.notes.store', $customer) }}" class="mb-3">
                @csrf
                <textarea name="note" class="form-control" rows="2" required placeholder="Add a timestamped note"></textarea>
                <button class="btn btn-sm btn-primary mt-2" style="background:var(--jt-primary);border-color:var(--jt-primary);">Add Note</button>
            </form>
            @forelse ($customer->notes as $note)
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
            <form method="POST" action="{{ route('agent.customers.followups.store', $customer) }}" class="mb-3">
                @csrf
                <input type="text" name="reason" class="form-control mb-2" placeholder="Reason" required>
                <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Notes"></textarea>
                <input type="datetime-local" name="scheduled_at" class="form-control mb-2" required>
                <button class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Schedule Follow-up</button>
            </form>
            @forelse ($customer->followUps as $followUp)
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
                        <form method="POST" action="{{ route('agent.customers.followups.complete', $followUp) }}" class="mt-2">
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
    <h5 class="mb-2">Interaction History</h5>
    @forelse ($customer->notes as $note)
        <div class="mb-2">
            <span class="jt-muted" style="font-size:12px;">{{ $note->created_at?->format('d M Y H:i') }} · {{ $note->salesRep?->name }}</span>
            <div>{{ $note->note }}</div>
        </div>
    @empty
        <div class="jt-muted">No interaction history yet.</div>
    @endforelse
</div>
@endsection
