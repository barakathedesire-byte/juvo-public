@extends('logistics.layouts.app')

@section('title', 'Rider profile')
@section('heading', 'Rider details')

@section('content')
    @php
        $tab = request('tab', 'overview');
        $tabs = [
            'overview' => 'Overview',
            'deliveries' => 'Deliveries',
            'performance' => 'Performance',
            'documents' => 'Documents',
            'vehicle' => 'Vehicle',
            'activity' => 'Activity',
        ];
    @endphp

    <div class="page-heading">
        <div>
            <div class="eyebrow">Fleet</div>
            <h2 class="page-title">{{ $rider->full_name }}</h2>
            <p class="page-subtitle">Rider operations, compliance, performance and delivery history.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($rider->isEligibleForWork())
                <span class="badge-status status-delivered">Eligible</span>
            @else
                <span class="badge-status status-failed">Not eligible</span>
            @endif
            <span class="badge-status {{ $rider->is_online ? 'status-online' : 'status-offline' }}">{{ $rider->is_online ? 'Online' : 'Offline' }}</span>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach ($tabs as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('logistics.riders.show', $rider) }}?tab={{ $key }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    @if ($tab === 'overview')
        <div class="row g-3">
            <div class="col-xl-4">
                <div class="surface">
                    <div class="card-body text-center">
                        <div class="rider-avatar-lg">{{ Str::upper(Str::substr($rider->first_name, 0, 1).Str::substr($rider->last_name ?? '', 0, 1)) }}</div>
                        <h4 class="mt-3 mb-1">{{ $rider->full_name }}</h4>
                        <div class="text-muted small">{{ $rider->city ?: 'Location unset' }}</div>
                        @if ($rider->profile_image)
                            <img src="{{ $rider->profile_image }}" alt="{{ $rider->full_name }}" class="mt-2 rounded-circle" style="width: 80px; height: 80px;">
                        @endif
                        <div class="mt-3">
                            @if ($rider->last_online_at)
                                <div class="text-muted small">Seen {{ $rider->last_online_at->diffForHumans() }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="surface">
                    <div class="card-header"><h5 class="mb-0">Profile</h5></div>
                    <div class="card-body">
                        <dl class="info-list">
                            <div class="info-row"><dt class="text-muted">Email</dt><dd>{{ $rider->email }}</dd></div>
                            <div class="info-row"><dt class="text-muted">Phone</dt><dd>{{ $rider->phone ?: 'Not provided' }}</dd></div>
                            <div class="info-row"><dt class="text-muted">Vehicle</dt><dd>{{ Str::headline($rider->vehicle_type ?? 'Not set') }}</dd></div>
                            <div class="info-row"><dt class="text-muted">License plate</dt><dd>{{ $rider->license_plate ?: '—' }}</dd></div>
                            <div class="info-row"><dt class="text-muted">License expiry</dt><dd>{{ $rider->license_expiry?->format('d M Y') ?: '—' }}</dd></div>
                            <div class="info-row"><dt class="text-muted">KYC status</dt><dd><span class="badge-status status-{{ $rider->kyc_status }}">{{ Str::headline($rider->kyc_status) }}</span></dd></div>
                            @unless ($rider->isEligibleForWork())
                                <div class="info-row"><dt class="text-danger">Eligibility</dt><dd class="text-danger">{{ $rider->eligibilityReason() }}</dd></div>
                            @endunless
                            <div class="info-row"><dt class="text-muted">Rating</dt><dd class="fw-semibold">{{ number_format((float) $rider->rating, 1) }} <i class="fas fa-star text-warning ms-1"></i></dd></div>
                            <div class="info-row"><dt class="text-muted">Total deliveries</dt><dd>{{ number_format($rider->total_deliveries ?? 0) }}</dd></div>
                            <div class="info-row"><dt class="text-muted">Earnings</dt><dd>{{ number_format((float) $metrics['earnings'], 0) }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($tab === 'deliveries')
        <div class="surface">
            <div class="card-header"><h5 class="mb-0">Recent deliveries</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Order</th><th>Status</th><th>Pickup</th><th>Destination</th><th>Assigned</th><th>Fee</th></tr></thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td><a href="{{ route('logistics.orders.show', $order) }}" class="link-juvo">#{{ $order->id }}</a></td>
                                    <td><span class="badge-status status-{{ $order->logistics_status }}">{{ $order->logisticsStatusLabel() }}</span></td>
                                    <td class="small">{{ $order->pickupLocation->name ?? '—' }}</td>
                                    <td class="small">{{ $order->shippingAddress->city ?? $order->shippingAddress->address ?? '—' }}</td>
                                    <td class="text-muted small">{{ $order->assigned_at?->format('d M Y H:i') ?? '—' }}</td>
                                    <td class="fw-semibold">{{ $order->currency ?? 'TZS' }} {{ number_format((float) $order->delivery_fee, 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted text-center py-4">No deliveries assigned yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @elseif ($tab === 'performance')
        <div class="row g-3">
            @foreach ([
                ['label' => 'Assigned', 'value' => number_format($metrics['assigned_total']), 'color' => 'blue'],
                ['label' => 'Delivered', 'value' => number_format($metrics['delivered']), 'color' => 'green'],
                ['label' => 'Failed', 'value' => number_format($metrics['failed']), 'color' => 'red'],
                ['label' => 'Cancelled', 'value' => number_format($metrics['cancelled']), 'color' => 'warning'],
                ['label' => 'Completion rate', 'value' => $metrics['completion_rate'] !== null ? $metrics['completion_rate'].'%' : '—', 'color' => 'orange'],
                ['label' => 'Acceptance rate', 'value' => $metrics['acceptance_rate'] !== null ? $metrics['acceptance_rate'].'%' : '—', 'color' => 'blue'],
            ] as $tile)
                <div class="col-6 col-lg-4">
                    <div class="surface metric-card is-{{ $tile['color'] }}">
                        <span class="metric-label">{{ $tile['label'] }}</span>
                        <span class="metric-value">{{ $tile['value'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="surface mt-3">
            <div class="card-body">
                <dl class="info-list">
                    <div class="info-row"><dt class="text-muted">Cash held</dt><dd>{{ number_format($metrics['cash_held'], 0) }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Earnings</dt><dd>{{ number_format($metrics['earnings'], 0) }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Accepted assignments</dt><dd>{{ number_format($metrics['accepted']) }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Declined assignments</dt><dd>{{ number_format($metrics['declined']) }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Rating</dt><dd>{{ number_format((float) $rider->rating, 1) }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Complaints</dt><dd>No complaint records are stored.</dd></div>
                </dl>
            </div>
        </div>
    @elseif ($tab === 'documents')
        <div class="surface">
            <div class="card-header"><h5 class="mb-0">Documents and verification</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Submitted documents</h6>
                        @if (! empty($documents))
                            <ul class="list-group list-group-flush">
                                @foreach ($documents as $key => $value)
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span>{{ Str::headline(str_replace('_', ' ', (string) $key)) }}</span>
                                        @if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL))
                                            <a href="{{ $value }}" target="_blank" rel="noopener" class="link-juvo small">View</a>
                                        @elseif (is_string($value) && $value !== '')
                                            <span class="text-muted small text-truncate" style="max-width: 180px;">{{ $value }}</span>
                                        @else
                                            <span class="text-muted small">Recorded</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="empty-state py-3">
                                <div class="icon"><i class="fas fa-file-circle-question"></i></div>
                                <strong>No documents submitted</strong>
                                <p>KYC documents will appear after rider onboarding.</p>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Missing required documents</h6>
                        @if (! empty($missingDocuments))
                            <ul class="list-group list-group-flush">
                                @foreach ($missingDocuments as $document)
                                    <li class="list-group-item px-0 text-danger">{{ Str::headline(str_replace('_', ' ', (string) $document)) }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="empty-state py-3">
                                <div class="icon"><i class="fas fa-circle-check"></i></div>
                                <strong>Document set complete</strong>
                                <p>All required KYC documents are present.</p>
                            </div>
                        @endif
                        <div class="small text-muted mt-3">KYC status: {{ Str::headline($rider->kyc_status) }}</div>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($tab === 'vehicle')
        <div class="surface">
            <div class="card-header"><h5 class="mb-0">Vehicle and license</h5></div>
            <div class="card-body">
                <dl class="info-list">
                    <div class="info-row"><dt class="text-muted">Vehicle type</dt><dd>{{ Str::headline($rider->vehicle_type ?? 'Not set') }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Make</dt><dd>{{ $rider->vehicle_make ?: '—' }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Model</dt><dd>{{ $rider->vehicle_model ?: '—' }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Year</dt><dd>{{ $rider->vehicle_year ?: '—' }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Color</dt><dd>{{ $rider->vehicle_color ?: '—' }}</dd></div>
                    <div class="info-row"><dt class="text-muted">License plate</dt><dd>{{ $rider->license_plate ?: '—' }}</dd></div>
                    <div class="info-row"><dt class="text-muted">License number</dt><dd>{{ $rider->license_number ?: '—' }}</dd></div>
                    <div class="info-row"><dt class="text-muted">License expiry</dt><dd>{{ $rider->license_expiry?->format('d M Y') ?: '—' }}</dd></div>
                    <div class="info-row"><dt class="text-muted">Background check</dt><dd>{{ Str::headline($rider->background_check_status ?: 'pending') }}</dd></div>
                </dl>
            </div>
        </div>
    @elseif ($tab === 'activity')
        <div class="surface">
            <div class="card-header"><h5 class="mb-0">Activity</h5></div>
            <div class="card-body">
                <div class="activity-feed">
                    @forelse ($events as $event)
                        <div class="activity-item">
                            <span class="activity-dot"><i class="fas fa-bolt"></i></span>
                            <div>
                                <p>
                                    <strong>{{ Str::headline($event->type) }}</strong>
                                    @if ($event->order) on <a href="{{ route('logistics.orders.show', $event->order) }}" class="link-juvo">#{{ $event->order_id }}</a> @else on order #{{ $event->order_id }} @endif
                                </p>
                                <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('d M Y H:i') }}</time>
                                @if ($event->note)<div class="small text-muted mt-1">{{ $event->note }}</div>@endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-state py-3">
                            <div class="icon"><i class="fas fa-stream"></i></div>
                            <strong>No activity yet</strong>
                            <p>Delivery transitions will be logged here.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 mt-3">
        @if ($rider->isKycPending())
            <form method="POST" action="{{ route('logistics.riders.approve', $rider) }}">@csrf<button class="btn btn-success btn-sm">Approve</button></form>
            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#rejectRider">Reject</button>
        @endif
        <form method="POST" action="{{ route('logistics.riders.toggle-active', $rider) }}">@csrf<button class="btn btn-outline-secondary btn-sm">{{ $rider->is_active ? 'Suspend' : 'Reactivate' }}</button></form>
    </div>
    <div class="collapse mt-2" id="rejectRider">
        <form method="POST" action="{{ route('logistics.riders.reject', $rider) }}" class="card card-body">
            @csrf
            <label class="form-label small">Rejection reason</label>
            <textarea name="reason" class="form-control mb-2" required maxlength="500"></textarea>
            <button class="btn btn-danger btn-sm">Reject rider</button>
        </form>
    </div>
@endsection
