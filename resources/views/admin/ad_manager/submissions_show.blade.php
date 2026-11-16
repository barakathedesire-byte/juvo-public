@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Review submission #{{ $campaign->id }}</h4>
        <a href="{{ route('admin.ad-manager.submissions.index') }}" class="btn btn-outline-secondary btn-sm">Back to queue</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Campaign</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Title</dt><dd class="col-sm-8">{{ $campaign->title }}</dd>
                        <dt class="col-sm-4">Objective</dt><dd class="col-sm-8">{{ ucfirst($campaign->objective ?? '—') }}</dd>
                        <dt class="col-sm-4">Placement</dt><dd class="col-sm-8">{{ $campaign->placement?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">Package</dt><dd class="col-sm-8">{{ $campaign->package?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">Billing model</dt><dd class="col-sm-8">{{ strtoupper($campaign->billing_model) }}</dd>
                        <dt class="col-sm-4">Schedule</dt>
                        <dd class="col-sm-8">
                            {{ optional($campaign->start_date)->toDateString() ?? '—' }}
                            &rarr; {{ optional($campaign->end_date)->toDateString() ?? '—' }}
                        </dd>
                        <dt class="col-sm-4">Total budget</dt><dd class="col-sm-8">{{ number_format($campaign->total_budget, 2) }}</dd>
                        <dt class="col-sm-4">Amount paid</dt><dd class="col-sm-8">{{ number_format($campaign->amount_paid, 2) }}</dd>
                        <dt class="col-sm-4">Payment</dt>
                        <dd class="col-sm-8">{{ $campaign->payment_method ?? '—' }} @if($campaign->paid_at)<span class="badge text-bg-success">paid</span>@endif</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Creative</div>
                <div class="card-body">
                    <h5 class="mb-1">{{ $campaign->creative_headline ?? '—' }}</h5>
                    <p class="mb-2 text-muted">{{ $campaign->creative_body }}</p>
                    @if($campaign->creative_image)
                        <img src="{{ asset('storage/'.$campaign->creative_image) }}" class="img-fluid rounded border mb-2" alt="creative">
                    @endif
                    @if($campaign->target_url)
                        <div class="small">Target: <a href="{{ $campaign->target_url }}" target="_blank" rel="noopener">{{ $campaign->target_url }}</a></div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Audit trail</div>
                <ul class="list-group list-group-flush">
                    @forelse($campaign->moderationLogs as $log)
                        <li class="list-group-item d-flex justify-content-between">
                            <div>
                                <span class="badge text-bg-secondary">{{ $log->action }}</span>
                                @if($log->from_status)<span class="small text-muted">{{ $log->from_status }} &rarr; {{ $log->to_status }}</span>@endif
                                @if($log->note)<div class="small">{{ $log->note }}</div>@endif
                            </div>
                            <div class="small text-muted text-end">
                                {{ $log->admin?->name ?? 'system' }}<br>{{ $log->created_at->diffForHumans() }}
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No activity recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Vendor</div>
                <div class="card-body">
                    <div class="fw-semibold">{{ $campaign->vendor?->shop_name ?? $campaign->vendor?->name ?? '—' }}</div>
                    <div class="small text-muted">{{ $campaign->vendor?->email }}</div>
                    <div class="mt-1">
                        @if($campaign->vendor?->isApproved())<span class="badge text-bg-success">approved</span>@else<span class="badge text-bg-danger">not approved</span>@endif
                        @if($campaign->vendor?->ad_restricted)<span class="badge text-bg-danger">ad restricted</span>@endif
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Decision</div>
                <div class="card-body">
                    <div class="mb-2">Current status: <span class="badge {{ $campaign->statusBadgeClass() }}">{{ $campaign->statusLabel() }}</span></div>

                    @if($campaign->status === 'submitted')
                        <form method="POST" action="{{ route('admin.ad-manager.submissions.review', $campaign->id) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-outline-secondary w-100">Take under review</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.ad-manager.submissions.approve', $campaign->id) }}" class="mb-2">
                        @csrf
                        <textarea name="admin_note" class="form-control mb-2" rows="2" placeholder="Optional note for approval…"></textarea>
                        <button class="btn btn-success w-100">Approve &amp; activate</button>
                    </form>

                    <form method="POST" action="{{ route('admin.ad-manager.submissions.changes', $campaign->id) }}" class="mb-2">
                        @csrf
                        <textarea name="admin_note" class="form-control mb-2" rows="2" placeholder="Describe the required changes…" required></textarea>
                        <button class="btn btn-info w-100 text-white">Request changes</button>
                    </form>

                    <form method="POST" action="{{ route('admin.ad-manager.submissions.reject', $campaign->id) }}"
                          onsubmit="return confirm('Reject this campaign?');">
                        @csrf
                        <textarea name="admin_note" class="form-control mb-2" rows="2" placeholder="Reason for rejection…" required></textarea>
                        <button class="btn btn-outline-danger w-100">Reject</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
