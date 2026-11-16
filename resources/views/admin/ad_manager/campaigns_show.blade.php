@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">{{ $campaign->title }} <span class="badge {{ $campaign->statusBadgeClass() }}">{{ $campaign->statusLabel() }}</span></h4>
        <a href="{{ route('admin.ad-manager.campaigns.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="row g-3 mb-3">
        @foreach([
            ['Impressions', number_format($campaign->impressions)],
            ['Clicks', number_format($campaign->clicks)],
            ['CTR', $campaign->ctr.'%'],
            ['Conversions', number_format($campaign->conversions)],
            ['CVR', $campaign->cvr.'%'],
            ['Spent / Budget', number_format($campaign->spent, 2).' / '.number_format($campaign->amount_paid, 2)],
        ] as [$label, $value])
            <div class="col-6 col-md-2">
                <div class="card shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-5 fw-bold">{{ $value }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Budget pacing</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ number_format($campaign->spent, 2) }} spent</span>
                        <span>{{ $campaign->budget_used_percent }}% used</span>
                    </div>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar" style="width:{{ min(100, $campaign->budget_used_percent) }}%;background:#f68b1e;"></div>
                    </div>
                    <div class="small text-muted mt-1">Remaining: {{ number_format($campaign->budget_remaining, 2) }}</div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Creative versions</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.ad-manager.campaigns.creatives.store', $campaign->id) }}" class="row g-2 mb-3">
                        @csrf
                        <div class="col-md-6"><input name="headline" class="form-control form-control-sm" placeholder="Headline"></div>
                        <div class="col-md-6"><input name="target_url" class="form-control form-control-sm" placeholder="Target URL (https://…)"></div>
                        <div class="col-md-9"><input name="body" class="form-control form-control-sm" placeholder="Body text"></div>
                        <div class="col-md-3"><input name="image" class="form-control form-control-sm" placeholder="Image path"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary">Add new version</button></div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>v</th><th>Headline</th><th>Target</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @forelse($campaign->creatives as $cr)
                                    <tr>
                                        <td>{{ $cr->version }}</td>
                                        <td>{{ $cr->headline ?? '—' }}</td>
                                        <td class="small text-muted text-truncate" style="max-width:160px;">{{ $cr->target_url ?? '—' }}</td>
                                        <td>
                                            @if($cr->is_approved)<span class="badge text-bg-success">live</span>
                                            @else<span class="badge text-bg-secondary">draft</span>@endif
                                        </td>
                                        <td class="text-end">
                                            @unless($cr->is_approved)
                                                <form method="POST" action="{{ route('admin.ad-manager.campaigns.creatives.approve', [$campaign->id, $cr->id]) }}">@csrf
                                                    <button class="btn btn-sm btn-outline-success">Approve</button></form>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">No versions yet — the current creative is inline.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Daily performance</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th class="text-end">Impressions</th><th class="text-end">Clicks</th><th class="text-end">Conversions</th><th class="text-end">Spend</th></tr></thead>
                        <tbody>
                            @forelse($campaign->dailyStats->sortByDesc('date') as $s)
                                <tr>
                                    <td>{{ $s->date->toDateString() }}</td>
                                    <td class="text-end">{{ number_format($s->impressions) }}</td>
                                    <td class="text-end">{{ number_format($s->clicks) }}</td>
                                    <td class="text-end">{{ number_format($s->conversions) }}</td>
                                    <td class="text-end">{{ number_format($s->spend, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No daily stats recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Audit trail</div>
                <ul class="list-group list-group-flush">
                    @forelse($campaign->moderationLogs as $log)
                        <li class="list-group-item">
                            <span class="badge text-bg-secondary">{{ $log->action }}</span>
                            @if($log->note)<span class="small">{{ $log->note }}</span>@endif
                            <span class="float-end small text-muted">{{ $log->created_at->diffForHumans() }} · {{ $log->admin?->name ?? 'system' }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No activity recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Vendor</dt><dd class="col-7">{{ $campaign->vendor?->shop_name ?? '—' }}</dd>
                        <dt class="col-5">Product</dt><dd class="col-7">{{ $campaign->product?->translation?->name ?? '—' }}</dd>
                        <dt class="col-5">Placement</dt><dd class="col-7">{{ $campaign->placement?->name ?? '—' }}</dd>
                        <dt class="col-5">Package</dt><dd class="col-7">{{ $campaign->package?->name ?? '—' }}</dd>
                        <dt class="col-5">Billing</dt><dd class="col-7">{{ strtoupper($campaign->billing_model) }}</dd>
                        <dt class="col-5">Start</dt><dd class="col-7">{{ optional($campaign->start_date)->toDateString() ?? '—' }}</dd>
                        <dt class="col-5">End</dt><dd class="col-7">{{ optional($campaign->end_date)->toDateString() ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Actions</div>
                <div class="card-body d-grid gap-2">
                    @if($campaign->status === 'active')
                        <form method="POST" action="{{ route('admin.ad-manager.campaigns.pause', $campaign->id) }}">@csrf
                            <button class="btn btn-outline-warning w-100">Pause</button></form>
                    @endif
                    @if(in_array($campaign->status, ['paused', 'scheduled']))
                        <form method="POST" action="{{ route('admin.ad-manager.campaigns.resume', $campaign->id) }}">@csrf
                            <button class="btn btn-outline-success w-100">Resume</button></form>
                    @endif
                    @if(in_array($campaign->status, ['active', 'paused']))
                        <form method="POST" action="{{ route('admin.ad-manager.campaigns.complete', $campaign->id) }}">@csrf
                            <button class="btn btn-outline-primary w-100">Mark completed</button></form>
                    @endif
                    @if(!in_array($campaign->status, ['cancelled', 'completed']))
                        <form method="POST" action="{{ route('admin.ad-manager.campaigns.cancel', $campaign->id) }}"
                              onsubmit="return confirm('Cancel this campaign?');">@csrf
                            <button class="btn btn-outline-danger w-100">Cancel</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.ad-manager.campaigns.destroy', $campaign->id) }}"
                          onsubmit="return confirm('Permanently delete this campaign?');">@csrf @method('DELETE')
                        <button class="btn btn-outline-dark w-100">Delete</button></form>
                </div>
            </div>
        </div>
    </div>
@endsection
