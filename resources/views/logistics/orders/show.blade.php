@extends('logistics.layouts.app')

@section('title', 'Delivery #'.$order->id)

@section('heading', 'Delivery #'.$order->id)

@section('content')
    @php
        $status = $order->logistics_status ?? 'pending';
        $currency = $order->currency ?? 'TZS';
        $customerName = trim(($order->customer->first_name ?? '').' '.($order->customer->last_name ?? '')) ?: 'Guest';
        $address = $order->shippingAddress;
        $terminal = in_array($status, ['delivered', 'returned', 'cancelled'], true);
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <a href="{{ route('logistics.orders.index') }}" class="link-juvo small"><i class="fas fa-arrow-left me-1"></i>Back to orders</a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status status-{{ $status }}">{{ $order->logisticsStatusLabel() }}</span>
            <span class="text-muted small">Tracking: {{ $order->logistics_tracking_id }}</span>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Customer</div>
                            <div class="fw-semibold">{{ $customerName }}</div>
                            <div class="small text-muted">{{ $address->phone ?? $order->customer->phone ?? 'No phone' }}</div>
                            <div class="small mt-2">
                                @if($address)
                                    {{ $address->address }}<br>
                                    {{ $address->city }}{{ $address->state ? ', '.$address->state : '' }}
                                @else
                                    <span class="text-muted">No delivery address on file</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Payment</div>
                            <div class="fw-semibold">{{ $currency }} {{ number_format((float) $order->total_amount, 2) }}</div>
                            <div class="small text-muted">
                                {{ ucwords(str_replace('_', ' ', (string) $order->payment_method)) }}
                                &middot; <span class="text-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }}">{{ $order->payment_status }}</span>
                            </div>
                            @if($order->isCod())
                                <div class="small mt-2">
                                    <span class="badge bg-light text-dark border">COD</span>
                                    @if($order->cod_collected_at)
                                        Collected {{ $currency }} {{ number_format((float) $order->cod_amount, 2) }}
                                        @if($order->cod_remitted_at)
                                            &middot; <span class="text-success">Reconciled</span>
                                        @else
                                            &middot; <span class="text-warning">Cash held</span>
                                        @endif
                                    @else
                                        &middot; <span class="text-muted">Awaiting collection</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Vendor</div>
                            <div class="fw-semibold">{{ $order->vendor->name ?? 'Juvo' }}</div>
                            <div class="small text-muted">{{ $order->pickupLocation->name ?? 'Pickup location not set' }}</div>
                            <div class="small text-muted">{{ $order->pickupLocation->address ?? '' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Schedule</div>
                            <div class="small">Placed {{ $order->created_at->format('d M Y H:i') }}</div>
                            @if($order->scheduled_for)
                                <div class="small text-info">Scheduled {{ $order->scheduled_for->format('d M Y') }}</div>
                            @endif
                            <div class="small">Attempts: <strong>{{ (int) $order->delivery_attempts }}</strong></div>
                            @if($order->failed_reason)
                                <div class="small text-danger">{{ \App\Models\Order::FAILURE_REASONS[$order->failed_reason] ?? $order->failed_reason }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-box-open me-2 text-warning"></i>Items</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Price</th></tr></thead>
                            <tbody>
                                @forelse($order->details as $detail)
                                    <tr>
                                        <td>{{ $detail->product->name ?? 'Item #'.$detail->product_id }}</td>
                                        <td class="text-center">{{ $detail->quantity }}</td>
                                        <td class="text-end">{{ $currency }} {{ number_format((float) $detail->price * $detail->quantity, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center py-3">No item lines recorded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-user-check me-2 text-warning"></i>Rider assignment</h6>
                </div>
                <div class="card-body">
                    @if($order->assignedRider)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-placeholder">{{ strtoupper(substr($order->assignedRider->first_name, 0, 1)) }}</div>
                                <div>
                                    <a href="{{ route('logistics.riders.show', $order->assignedRider) }}" class="fw-semibold link-juvo">{{ $order->assignedRider->full_name }}</a>
                                    <div class="small text-muted">{{ $order->assignedRider->phone }} &middot; {{ ucfirst($order->assignedRider->vehicle_type ?? 'rider') }}</div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge-status status-{{ $order->assignedRider->is_online ? 'delivered' : 'cancelled' }}">{{ $order->assignedRider->is_online ? 'Online' : 'Offline' }}</span>
                                @unless($terminal)
                                    <form method="POST" action="{{ route('logistics.orders.unassign', $order) }}" onsubmit="return confirm('Remove this rider from the delivery?')">
                                        @csrf
                                        <input type="hidden" name="reason" value="Removed by dispatcher">
                                        <button class="btn btn-sm btn-outline-danger">Unassign</button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    @else
                        <p class="text-muted small mb-3">No rider is assigned yet.</p>
                    @endif

                    @unless($terminal)
                        <form method="POST" action="{{ route('logistics.orders.assign', $order) }}" class="row g-2 align-items-end mt-2">
                            @csrf
                            <div class="col-md-8">
                                <label class="form-label small text-muted mb-1">{{ $order->assignedRider ? 'Reassign to' : 'Assign rider' }}</label>
                                <select name="rider_id" class="form-select" required>
                                    <option value="">Choose an eligible rider…</option>
                                    @foreach($riders as $rider)
                                        <option value="{{ $rider->id }}" @selected((int) $order->assigned_driver_id === (int) $rider->id)>
                                            {{ $rider->full_name }} — {{ $rider->is_online ? 'online' : 'offline' }}{{ $rider->vehicle_type ? ' · '.ucfirst($rider->vehicle_type) : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 d-grid">
                                <button class="btn btn-warning text-white">{{ $order->assignedRider ? 'Reassign' : 'Assign' }}</button>
                            </div>
                        </form>
                        @if($riders->isEmpty())
                            <div class="small text-danger mt-2">No approved, active riders are available yet.</div>
                        @endif
                    @endunless
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-flag me-2 text-warning"></i>Priority and dispatch</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('logistics.orders.priority', $order) }}" class="row g-2 align-items-end mb-3">
                        @csrf
                        <div class="col-md-8">
                            <label class="form-label small text-muted mb-1">Delivery priority</label>
                            <select name="priority" class="form-select">
                                @foreach (['low', 'normal', 'high', 'urgent'] as $option)
                                    <option value="{{ $option }}" @selected(($order->logistics_priority ?: 'normal') === $option)>{{ Str::headline($option) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-grid">
                            <button class="btn btn-outline-secondary">Update priority</button>
                        </div>
                    </form>
                    @unless($terminal)
                        <form method="POST" action="{{ route('logistics.orders.auto-assign', $order) }}">
                            @csrf
                            <button class="btn btn-outline-secondary w-100">
                                <i class="fas fa-wand-magic-sparkles me-1"></i>Auto-assign best rider
                            </button>
                        </form>
                        <div class="small text-muted mt-2">Recommendations use availability, workload, compliance, and service-area match. Distance is not estimated unless coordinates are available.</div>
                    @endunless
                </div>
            </div>

            @if($order->pod_receiver_name)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-signature me-2 text-warning"></i>Proof of delivery</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted">Received by</div>
                                <div class="fw-semibold">{{ $order->pod_receiver_name }}</div>
                                <div class="small text-muted mt-2">{{ $order->pod_notes }}</div>
                            </div>
                            <div class="col-md-6">
                                @if($order->pod_photo_url)
                                    <a href="{{ asset('storage/'.$order->pod_photo_url) }}" target="_blank" rel="noopener">
                                        <img src="{{ asset('storage/'.$order->pod_photo_url) }}" alt="Proof of delivery" class="img-fluid rounded border" style="max-height: 220px;">
                                    </a>
                                @else
                                    <span class="text-muted small">No photo attached.</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-bolt me-2 text-warning"></i>Actions</h6></div>
                <div class="card-body d-grid gap-2">
                    @if(in_array($status, ['assigned', 'accepted'], true))
                        <form method="POST" action="{{ route('logistics.orders.pickup', $order) }}">@csrf
                            <button class="btn btn-warning text-white w-100">Mark picked up</button>
                        </form>
                    @endif
                    @if($status === 'picked_up')
                        <form method="POST" action="{{ route('logistics.orders.transit', $order) }}">@csrf
                            <button class="btn btn-warning text-white w-100">Mark out for delivery</button>
                        </form>
                    @endif
                    @if(in_array($status, ['picked_up', 'in_transit', 'accepted', 'assigned'], true))
                        <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#deliverForm">Mark delivered</button>
                    @endif
                    @if(in_array($status, ['assigned', 'accepted', 'picked_up', 'in_transit'], true))
                        <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#failForm">Report failed attempt</button>
                    @endif
                    @if($status === 'failed')
                        <button class="btn btn-outline-warning" data-bs-toggle="collapse" data-bs-target="#rescheduleForm">Reschedule</button>
                        <form method="POST" action="{{ route('logistics.orders.return', $order) }}" onsubmit="return confirm('Mark this delivery returned to sender?')">@csrf
                            <button class="btn btn-outline-secondary w-100">Return to sender</button>
                        </form>
                    @endif
                    @unless($terminal)
                        <form method="POST" action="{{ route('logistics.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this delivery?')">@csrf
                            <button class="btn btn-outline-danger w-100">Cancel delivery</button>
                        </form>
                    @endunless
                    @if($order->isCod() && $status === 'delivered' && ! $order->cod_collected_at)
                        <form method="POST" action="{{ route('logistics.orders.cod.collect', $order) }}">@csrf
                            <input type="hidden" name="amount" value="{{ $order->total_amount }}">
                            <button class="btn btn-outline-success w-100">Record cash collected</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="collapse mb-4" id="deliverForm">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Confirm delivery</h6>
                        <form method="POST" action="{{ route('logistics.orders.deliver', $order) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Received by</label>
                                <input type="text" name="receiver_name" class="form-control" required maxlength="120" value="{{ old('receiver_name', $order->pod_receiver_name) }}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Notes</label>
                                <textarea name="notes" rows="2" class="form-control" maxlength="1000">{{ old('notes') }}</textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Photo (optional)</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
                            </div>
                            @if($order->isCod() && $order->payment_status !== 'paid')
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="cod_collected" value="1" id="codCollected">
                                    <label class="form-check-label small" for="codCollected">Cash collected ({{ $currency }} {{ number_format((float) $order->total_amount, 2) }})</label>
                                </div>
                            @endif
                            <button class="btn btn-success w-100">Confirm delivered</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="collapse mb-4" id="failForm">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Failed attempt</h6>
                        <form method="POST" action="{{ route('logistics.orders.fail', $order) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Reason</label>
                                <select name="reason" class="form-select" required>
                                    <option value="">Choose a reason…</option>
                                    @foreach($failureReasons as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Note</label>
                                <textarea name="note" rows="2" class="form-control" maxlength="1000"></textarea>
                            </div>
                            <button class="btn btn-danger w-100">Record failed attempt</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="collapse mb-4" id="rescheduleForm">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Reschedule delivery</h6>
                        <form method="POST" action="{{ route('logistics.orders.reschedule', $order) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">New date</label>
                                <input type="date" name="scheduled_for" class="form-control" required min="{{ now()->format('Y-m-d') }}" value="{{ optional($order->scheduled_for)->format('Y-m-d') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Note</label>
                                <textarea name="note" rows="2" class="form-control" maxlength="1000"></textarea>
                            </div>
                            <button class="btn btn-warning text-white w-100">Reschedule</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="fas fa-clock-rotate-left me-2 text-warning"></i>Timeline</h6>
                    <span class="small text-muted">{{ $order->deliveryEvents->count() }} events</span>
                </div>
                <div class="card-body">
                    @if($order->deliveryEvents->isEmpty())
                        <p class="text-muted small mb-0">No delivery events yet.</p>
                    @else
                        <div class="timeline">
                            @foreach($order->deliveryEvents as $event)
                                <div class="timeline-item">
                                    <div class="small fw-semibold">{{ ucwords(str_replace('_', ' ', $event->type)) }}</div>
                                    <div class="small text-muted">
                                        {{ $event->created_at->format('d M Y H:i') }}
                                        @if($event->actor_name) &middot; {{ $event->actor_name }} @endif
                                    </div>
                                    @if($event->note)<div class="small">{{ $event->note }}</div>@endif
                                    @if($event->to_status)<div class="small text-muted">→ {{ ucwords(str_replace('_', ' ', $event->to_status)) }}</div>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-pen me-2 text-warning"></i>Internal note</h6>
                    <form method="POST" action="{{ route('logistics.orders.note', $order) }}">
                        @csrf
                        <textarea name="note" rows="2" class="form-control mb-2" required maxlength="1000" placeholder="Visible to logistics staff only"></textarea>
                        <button class="btn btn-outline-secondary btn-sm w-100">Add note</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
