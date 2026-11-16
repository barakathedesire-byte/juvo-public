@extends('logistics.layouts.app')

@section('title', 'Riders')
@section('heading', 'Rider network')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">Fleet</div>
            <h2 class="page-title">Riders</h2>
            <p class="page-subtitle">Capacity, compliance, and delivery performance in one directory.</p>
        </div>
        <a href="{{ route('logistics.riders.index', ['kyc' => 'under_review']) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-id-card me-1"></i>{{ $summary['pending_kyc'] }} KYC to review
        </a>
    </div>

    <div class="row g-2 mb-3">
        @foreach ([
            ['label' => 'Network', 'value' => $summary['total'], 'hint' => 'Registered riders', 'color' => 'orange'],
            ['label' => 'Online', 'value' => $summary['online'], 'hint' => 'Available now', 'color' => 'green'],
            ['label' => 'Eligible', 'value' => $summary['eligible'], 'hint' => 'Cleared to deliver', 'color' => 'blue'],
            ['label' => 'KYC review', 'value' => $summary['pending_kyc'], 'hint' => 'Pending checks', 'color' => 'warning'],
            ['label' => 'Suspended', 'value' => $summary['suspended'], 'hint' => 'Inactive accounts', 'color' => 'red'],
        ] as $tile)
            <div class="col-6 col-lg">
                <div class="surface metric-card is-{{ $tile['color'] }}">
                    <span class="metric-label">{{ $tile['label'] }}</span>
                    <span class="metric-value">{{ number_format($tile['value']) }}</span>
                    <span class="metric-hint">{{ $tile['hint'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('logistics.riders.index') }}" class="filter-bar mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Search</label>
                <div class="position-relative">
                    <i class="fas fa-magnifying-glass position-absolute" style="left: .8rem; top: 50%; transform: translateY(-50%); color: #8c97a8; font-size: .72rem;"></i>
                    <input type="search" name="q" value="{{ $search }}" class="form-control ps-4" placeholder="Name, email, or phone">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">KYC status</label>
                <select name="kyc" class="form-select">
                    <option value="">All</option>
                    @foreach ($kycStatuses as $option)
                        <option value="{{ $option }}" @selected($kyc === $option)>{{ Str::headline($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Availability</label>
                <select name="availability" class="form-select">
                    <option value="">Any</option>
                    <option value="online" @selected($availability === 'online')>Online</option>
                    <option value="offline" @selected($availability === 'offline')>Offline</option>
                    <option value="suspended" @selected($availability === 'suspended')>Suspended</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Sort</label>
                <select name="sort" class="form-select">
                    <option value="">Newest</option>
                    <option value="online" @selected($sort === 'online')>Online first</option>
                    <option value="deliveries" @selected($sort === 'deliveries')>Deliveries</option>
                    <option value="rating" @selected($sort === 'rating')>Rating</option>
                </select>
            </div>
            <div class="col-6 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-juvo flex-fill">Apply</button>
                <a href="{{ route('logistics.riders.index') }}" class="btn btn-outline-secondary" aria-label="Reset filters"><i class="fas fa-rotate-left"></i></a>
            </div>
        </div>
    </form>

    <div class="surface">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Contact</th>
                        <th>Vehicle</th>
                        <th>KYC</th>
                        <th>Active</th>
                        <th>Completed</th>
                        <th>Rating</th>
                        <th>Availability</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riders as $rider)
                        @php($eligible = $rider->isEligibleForWork())
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-placeholder">{{ Str::upper(Str::substr($rider->first_name, 0, 1).Str::substr($rider->last_name ?? '', 0, 1)) }}</div>
                                    <div>
                                        <a href="{{ route('logistics.riders.show', $rider) }}" class="fw-semibold text-decoration-none" style="color: var(--juvo-ink);">{{ $rider->full_name }}</a>
                                        <div class="text-muted" style="font-size: .66rem;">{{ $rider->city ?: 'Location unset' }} · Joined {{ $rider->created_at->format('M Y') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>{{ $rider->email }}</div>
                                <div class="text-muted" style="font-size: .66rem;">{{ $rider->phone }}</div>
                            </td>
                            <td>
                                <div>{{ Str::headline($rider->vehicle_type ?? 'Not set') }}</div>
                                <div class="text-muted" style="font-size: .66rem;">{{ $rider->license_plate ?: 'No plate' }}</div>
                            </td>
                            <td>
                                <span class="badge-status status-{{ $rider->kyc_status }}">{{ Str::headline($rider->kyc_status) }}</span>
                                @unless ($eligible)
                                    <div class="text-danger mt-1" style="font-size: .64rem;">{{ $rider->eligibilityReason() }}</div>
                                @endunless
                            </td>
                            <td>
                                @if ($rider->active_deliveries_count > 0)
                                    <span class="badge-status status-in_transit">{{ $rider->active_deliveries_count }} live</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ number_format($rider->total_deliveries ?? 0) }}</td>
                            <td>
                                @if ($rider->rating)
                                    <span class="fw-semibold">{{ number_format((float) $rider->rating, 1) }}</span>
                                    <i class="fas fa-star text-warning ms-1" style="font-size: .62rem;"></i>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge-status {{ $rider->is_online ? 'status-online' : 'status-offline' }}">{{ $rider->is_online ? 'Online' : 'Offline' }}</span>
                                @if ($rider->last_online_at)
                                    <div class="text-muted mt-1" style="font-size: .64rem;">Seen {{ $rider->last_online_at->diffForHumans() }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="icon"><i class="fas fa-motorcycle"></i></div>
                                    <strong>No matching riders</strong>
                                    <p>Clear the filters or wait for rider applications.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($riders->hasPages())
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="small text-muted">Page {{ $riders->currentPage() }} of {{ $riders->lastPage() }}</span>
                {{ $riders->links() }}
            </div>
        @endif
    </div>
@endsection
