@extends('agent.layouts.agent')

@section('title', 'Welcome')

@section('content')
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of($agentProfile->name ?? auth()->user()->name ?? 'Consultant')->explode(' ')->first();
    $available = (float) ($balance['available'] ?? 0);
    $pending = (float) ($balance['pending'] ?? 0);
    $held = (float) ($balance['held'] ?? 0);
    $paid = (float) ($balance['paid'] ?? 0);
    $delivered = (int) ($stats['delivered'] ?? 0);
    $volume = (float) ($stats['volume'] ?? 0);
    $quality = $stats['quality'] ?? null;
    $rankName = $rank->name ?? 'Unranked';
    $rankColor = $rank->badge_color ?? '#6c757d';
    $nextRankName = $next_rank->name ?? null;
    $currency = $agentProfile->currency ?? 'TZS';

    $statCards = [
        ['label' => 'Available', 'value' => number_format($available, 2), 'suffix' => $currency, 'icon' => 'fas fa-wallet'],
        ['label' => 'Pending', 'value' => number_format($pending, 2), 'suffix' => $currency, 'icon' => 'fas fa-hourglass-half'],
        ['label' => 'Delivered orders', 'value' => number_format($delivered), 'suffix' => 'orders', 'icon' => 'fas fa-box-circle-check'],
        ['label' => 'Sales volume', 'value' => number_format($volume, 2), 'suffix' => $currency, 'icon' => 'fas fa-chart-line'],
    ];

    $quickActions = [
        ['route' => 'agent.place-order.index', 'label' => 'Place an order', 'sub' => 'Create an order for a customer', 'icon' => 'fas fa-cart-plus'],
        ['route' => 'agent.customers.index', 'label' => 'My customers', 'sub' => 'View and follow up', 'icon' => 'fas fa-users'],
        ['route' => 'agent.shop.index', 'label' => 'Browse catalogue', 'sub' => 'Find products to sell', 'icon' => 'fas fa-store'],
        ['route' => 'agent.commissions.index', 'label' => 'Commission ledger', 'sub' => 'Track what you have earned', 'icon' => 'fas fa-coins'],
    ];
@endphp

<section class="jt-hero jt-reveal">
    <div class="jt-hero-kicker">JTeam Sales Console</div>
    <h1>{{ $greeting }}, {{ $firstName }}.</h1>
    <p>Welcome back. This is your personal JTeam workspace — manage customers, place orders, track commissions and grow your sales, all in one place.</p>
    <div class="jt-hero-actions">
        <a href="{{ route('agent.place-order.index') }}" class="jt-btn-light">
            <i class="fas fa-cart-plus"></i> Place customer order
        </a>
        <a href="{{ route('agent.commissions.index') }}" class="jt-btn-ghost">
            <i class="fas fa-coins"></i> View commissions
        </a>
    </div>
    <div class="jt-hero-meta">
        <div><strong>{{ $rankName }}</strong><span>Current rank</span></div>
        <div><strong>{{ $quality !== null ? $quality.'%' : '—' }}</strong><span>Quality score</span></div>
        <div><strong>{{ number_format($leads_total ?? 0) }}</strong><span>Total leads</span></div>
        <div><strong>{{ number_format($attributed_orders ?? 0) }}</strong><span>Attributed orders</span></div>
    </div>
</section>

@if (($open_fraud ?? 0) > 0)
    <div class="alert alert-warning mt-3 jt-reveal jt-reveal-2">
        You have {{ $open_fraud }} open compliance review(s). Payouts may be on hold until resolved.
    </div>
@endif

<div class="row g-3 mt-1">
    @foreach ($statCards as $i => $card)
        <div class="col-6 col-xl-3">
            <div class="jt-card jt-stat jt-reveal jt-reveal-{{ min($i + 1, 4) }}">
                <div class="jt-stat-icon"><i class="{{ $card['icon'] }}"></i></div>
                <div class="jt-stat-label">{{ $card['label'] }}</div>
                <div class="jt-stat-value">{{ $card['value'] }} <span class="jt-muted" style="font-size:12px;font-weight:700;">{{ $card['suffix'] }}</span></div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="jt-card p-3 p-md-4 h-100 jt-reveal jt-reveal-2">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="jt-section-title"><i class="fas fa-bolt me-2" style="color:var(--jt-primary);"></i>Quick actions</h2>
                <span class="jt-muted" style="font-size:12px;">Your daily tools</span>
            </div>
            <div class="row g-2">
                @foreach ($quickActions as $action)
                    <div class="col-sm-6">
                        <a href="{{ route($action['route']) }}" class="jt-quick">
                            <span class="jt-quick-icon"><i class="{{ $action['icon'] }}"></i></span>
                            <span>
                                <strong>{{ $action['label'] }}</strong>
                                <span>{{ $action['sub'] }}</span>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="jt-card p-3 p-md-4 h-100 jt-reveal jt-reveal-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="jt-section-title"><i class="fas fa-trophy me-2" style="color:var(--jt-primary);"></i>Your rank</h2>
                <span class="badge" style="background:{{ $rankColor }};">{{ $rankName }}</span>
            </div>
            @if ($nextRankName)
                <div class="d-flex justify-content-between jt-muted" style="font-size:12px;">
                    <span>Progress to {{ $nextRankName }}</span>
                    <span>{{ $rank_progress ?? 0 }}%</span>
                </div>
                <div class="progress mt-2" style="height:9px;border-radius:9px;background:#f1f1f4;">
                    <div class="progress-bar" style="width:{{ $rank_progress ?? 0 }}%;background:linear-gradient(135deg,var(--jt-primary),var(--jt-accent));border-radius:9px;"></div>
                </div>
            @else
                <p class="jt-muted mb-0" style="font-size:13px;">You have reached the top rank. Keep delivering to maintain it.</p>
            @endif
            <div class="row g-2 mt-3">
                <div class="col-6">
                    <div class="p-2 rounded-3" style="background:#f8f9fb;">
                        <div class="jt-stat-label">Held</div>
                        <div class="fw-bold">{{ number_format($held, 2) }} <span class="jt-muted" style="font-size:11px;">{{ $currency }}</span></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 rounded-3" style="background:#f8f9fb;">
                        <div class="jt-stat-label">Paid to date</div>
                        <div class="fw-bold">{{ number_format($paid, 2) }} <span class="jt-muted" style="font-size:11px;">{{ $currency }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="jt-card p-3 p-md-4 h-100 jt-reveal jt-reveal-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="jt-section-title"><i class="fas fa-coins me-2" style="color:var(--jt-primary);"></i>Recent commission entries</h2>
                <a href="{{ route('agent.commissions.index') }}" class="btn btn-sm btn-outline-dark">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Order</th><th>Role</th><th class="text-end">Amount</th><th>State</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse ($recent_entries as $entry)
                            <tr>
                                <td>#{{ $entry->order_id }}</td>
                                <td>{{ ucfirst($entry->role) }}</td>
                                <td class="text-end">{{ number_format((float) $entry->amount, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst($entry->state) }}</span></td>
                                <td>{{ $entry->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="jt-muted text-center py-3">No commissions yet. Share your referral link to start earning.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="jt-card p-3 p-md-4 h-100 jt-reveal jt-reveal-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="jt-section-title"><i class="fas fa-wallet me-2" style="color:var(--jt-primary);"></i>Recent payouts</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Reference</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recent_payouts as $payout)
                            <tr><td>{{ $payout->reference }}</td><td class="text-end">{{ number_format((float) $payout->amount, 2) }}</td><td><span class="badge bg-secondary">{{ ucfirst($payout->status) }}</span></td></tr>
                        @empty
                            <tr><td colspan="3" class="jt-muted text-center py-3">No payouts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
