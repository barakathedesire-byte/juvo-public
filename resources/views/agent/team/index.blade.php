@extends('agent.layouts.agent')

@section('title', 'My Team / Referrals')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <h2 class="jt-section-title mb-1">My Team / Referrals</h2>
    <p class="jt-muted mb-0">Recruit consultants, refer customers, track team performance and rewards.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-3">
    @foreach ([
        ['Direct recruits', $metrics['direct_members']],
        ['Active', $metrics['active_members']],
        ['Inactive', $metrics['inactive_members']],
        ['Pending invitations', $metrics['pending_invitations']],
        ['New this month', $metrics['new_members_this_month']],
        ['Team orders', $metrics['team_orders']],
        ['Team sales', number_format($metrics['team_sales_value'],2)],
        ['Team customers', $metrics['team_customers']],
        ['Eligible team bonuses', number_format($metrics['eligible_bonuses'],2)],
    ] as $card)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="jt-card p-3 h-100">
                <div class="jt-muted" style="font-size:12px;">{{ $card[0] }}</div>
                <div class="fw-bold" style="font-size:20px;">{{ $card[1] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Consultant Invite</h5>
    <p class="jt-muted">Share this link or code with potential sales consultants.</p>
    <div class="input-group mb-2">
        <input type="text" class="form-control" value="{{ $consultantInviteLink }}" readonly>
        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ $consultantInviteLink }}')">Copy</button>
    </div>
    <div class="input-group mb-3">
        <input type="text" class="form-control" value="{{ $agent->code }}" readonly>
        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ $agent->code }}')">Copy Code</button>
    </div>
    <a href="https://wa.me/?text={{ urlencode('Join JTeam: '.$consultantInviteLink) }}" target="_blank" class="btn btn-sm btn-success">Share on WhatsApp</a>

    <hr>
    <h6>Send Invitation</h6>
    <form method="POST" action="{{ route('agent.team.invitations.store') }}" class="row g-2">
        @csrf
        <div class="col-md-4">
            <input type="text" name="name" class="form-control" placeholder="Name">
        </div>
        <div class="col-md-4">
            <input type="email" name="email" class="form-control" placeholder="Email">
        </div>
        <div class="col-md-4">
            <input type="text" name="phone" class="form-control" placeholder="Phone">
        </div>
        <div class="col-12">
            <button class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Create Invitation</button>
        </div>
    </form>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Customer Referral Link</h5>
    <div class="input-group">
        <input type="text" class="form-control" value="{{ $customerReferralLink }}" readonly>
        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ $customerReferralLink }}')">Copy</button>
    </div>
    <p class="jt-muted mt-2 mb-0">Use this link for customer referrals. Consultant and customer referrals are tracked separately.</p>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">My Team</h5>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Member</th><th>Status</th><th>Joined</th><th>Orders</th><th>Sales</th><th>Customers</th><th>Converted Leads</th></tr></thead>
            <tbody>
                @forelse ($teamPerformance as $row)
                    <tr>
                        <td>{{ $row['member']->name }}</td>
                        <td>{{ $row['member']->status }}</td>
                        <td>{{ $row['member']->created_at?->format('d M Y') }}</td>
                        <td>{{ $row['orders'] }}</td>
                        <td>{{ number_format($row['sales'], 2) }}</td>
                        <td>{{ $row['customers'] }}</td>
                        <td>{{ $row['leads_converted'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center jt-muted py-3">No team members yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Invitations</h5>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Name</th><th>Email/Phone</th><th>Status</th><th>Expires</th></tr></thead>
            <tbody>
                @forelse ($invitations as $invitation)
                    <tr>
                        <td>{{ $invitation->name ?: '—' }}</td>
                        <td>{{ $invitation->email ?: $invitation->phone }}</td>
                        <td>{{ $invitation->status }}</td>
                        <td>{{ $invitation->expires_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center jt-muted py-3">No invitations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">{{ $invitations->links() }}</div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Customer Referrals</h5>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Lead</th><th>Status</th><th>Source</th><th>Created</th></tr></thead>
            <tbody>
                @forelse ($customerReferrals as $lead)
                    <tr>
                        <td>{{ $lead->name }}</td>
                        <td>{{ $lead->status }}</td>
                        <td>{{ \App\Models\SalesLead::SOURCES[$lead->source] ?? $lead->source }}</td>
                        <td>{{ $lead->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center jt-muted py-3">No customer referrals yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">{{ $customerReferrals->links() }}</div>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Team Rewards</h5>
    <table class="table table-sm mb-0">
        <thead><tr><th>Order</th><th>Role</th><th>Amount</th><th>State</th><th>Date</th></tr></thead>
        <tbody>
            @forelse ($rewards as $reward)
                <tr>
                    <td>#{{ $reward->order_id }}</td>
                    <td>{{ $reward->role }}</td>
                    <td>{{ number_format((float) $reward->amount, 2) }}</td>
                    <td>{{ $reward->state }}</td>
                    <td>{{ $reward->created_at?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center jt-muted py-3">No team rewards yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <h5 class="mb-2">Activity Feed</h5>
    @forelse ($activity as $item)
        <div class="mb-2">
            <span class="jt-muted" style="font-size:12px;">{{ $item['date']?->format('d M Y H:i') }}</span>
            <div><strong>{{ $item['type'] }}:</strong> {{ $item['label'] }}</div>
        </div>
    @empty
        <div class="jt-muted">No recent activity.</div>
    @endforelse
</div>
@endsection
