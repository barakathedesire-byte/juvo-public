@extends('rider.layouts.app')

@section('title', 'Earnings')
@section('body_class', 'rider-earnings')

@php
    $rangeLabels = [
        'today' => 'Today',
        '7d' => '7 Days',
        '30d' => '30 Days',
        'custom' => 'Custom',
    ];

    $typeLabels = [
        'all' => 'All Transactions',
        'delivery_earnings' => 'Delivery Earnings',
        'bonuses' => 'Bonuses',
        'adjustments' => 'Adjustments',
        'deductions' => 'Deductions',
        'payouts' => 'Payouts',
    ];

    $periodLabel = $range === 'today'
        ? 'Today'
        : ($range === '7d' ? 'Last 7 days' : ($range === '30d' ? 'Last 30 days' : $startDay->format('d M').' – '.$endDay->format('d M Y')));
@endphp

@section('hero')
    @include('rider.partials.deliveries-header', [
        'title' => 'My Earnings',
        'subtitle' => 'Financial summary, transactions and payout status.',
        'eyebrow' => 'Juvo Rider',
        'unreadCount' => $unreadCount,
        'showRefresh' => true,
    ])
@endsection

@section('content')
    <section class="earnings-page" aria-label="Earnings">
        <form method="GET" action="{{ route('rider.earnings') }}" class="earnings-filter-form" data-earnings-filter>
            <input type="hidden" name="range" value="{{ $range }}" data-range-input>
            <input type="hidden" name="type" value="{{ $type }}" data-type-input>

            <div class="earnings-range-tabs" role="tablist" aria-label="Date range">
                @foreach (['today', '7d', '30d', 'custom'] as $option)
                    <button type="button"
                            class="earnings-range-tab {{ $range === $option ? 'is-active' : '' }}"
                            data-range="{{ $option }}"
                            role="tab"
                            aria-selected="{{ $range === $option ? 'true' : 'false' }}">
                        {{ $rangeLabels[$option] }}
                    </button>
                @endforeach
            </div>

            <div class="earnings-custom-range {{ $range === 'custom' ? 'is-open' : '' }}" data-custom-range>
                <label>
                    <span>From</span>
                    <input type="date" name="from" value="{{ old('from', request('from', $startDay->toDateString())) }}">
                </label>
                <label>
                    <span>To</span>
                    <input type="date" name="to" value="{{ old('to', request('to', $endDay->toDateString())) }}">
                </label>
                <button type="submit" class="delivery-btn delivery-btn-primary">Apply</button>
            </div>
        </form>

        <article class="earnings-hero-card" aria-labelledby="availableEarningsTitle">
            <div class="earnings-hero-copy">
                <span id="availableEarningsTitle">Total available earnings</span>
                <strong>{{ $currency }} {{ number_format($allTimeTotal, 0) }}</strong>
                <p>Approved delivery earnings eligible for payout. COD collected from customers is not counted as rider earnings.</p>
            </div>
            <div class="earnings-hero-meta">
                <div>
                    <span>Available balance</span>
                    <strong>{{ $currency }} {{ number_format($allTimeTotal, 0) }}</strong>
                </div>
                <div>
                    <span>Pending earnings</span>
                    <strong>{{ $currency }} {{ number_format($pendingTotal, 0) }}</strong>
                </div>
                <div>
                    <span>Total paid</span>
                    <strong>{{ $currency }} 0</strong>
                </div>
            </div>
        </article>

        <div class="earnings-breakdown-grid">
            <article class="earnings-stat-card">
                <span>Today</span>
                <strong>{{ $currency }} {{ number_format($todayTotal, 0) }}</strong>
                <small>{{ $todayCount }} {{ Str::plural('delivery', $todayCount) }}</small>
            </article>
            <article class="earnings-stat-card">
                <span>This week</span>
                <strong>{{ $currency }} {{ number_format($weekTotal, 0) }}</strong>
                <small>{{ $weekCount }} {{ Str::plural('delivery', $weekCount) }}</small>
            </article>
            <article class="earnings-stat-card">
                <span>This month</span>
                <strong>{{ $currency }} {{ number_format($monthTotal, 0) }}</strong>
                <small>{{ $monthCount }} {{ Str::plural('delivery', $monthCount) }}</small>
            </article>
            <article class="earnings-stat-card">
                <span>{{ $periodLabel }}</span>
                <strong>{{ $currency }} {{ number_format($periodTotal, 0) }}</strong>
                <small>{{ $periodCount }} {{ Str::plural('delivery', $periodCount) }} · avg {{ number_format($periodAverage, 0) }}</small>
            </article>
        </div>

        <div class="earnings-summary-grid">
            <article class="earnings-summary-card">
                <div class="earnings-summary-icon is-available"><i class="fas fa-wallet" aria-hidden="true"></i></div>
                <div>
                    <span>Available balance</span>
                    <strong>{{ $currency }} {{ number_format($allTimeTotal, 0) }}</strong>
                    <small>Approved earnings eligible for payout.</small>
                </div>
            </article>
            <article class="earnings-summary-card">
                <div class="earnings-summary-icon is-pending"><i class="fas fa-hourglass-half" aria-hidden="true"></i></div>
                <div>
                    <span>Pending earnings</span>
                    <strong>{{ $currency }} {{ number_format($pendingTotal, 0) }}</strong>
                    <small>Estimated from active deliveries not yet completed.</small>
                </div>
            </article>
            <article class="earnings-summary-card">
                <div class="earnings-summary-icon is-paid"><i class="fas fa-circle-check" aria-hidden="true"></i></div>
                <div>
                    <span>Total paid</span>
                    <strong>{{ $currency }} 0</strong>
                    <small>No rider payout records are available in this workspace.</small>
                </div>
            </article>
            <article class="earnings-summary-card">
                <div class="earnings-summary-icon is-deliveries"><i class="fas fa-motorcycle" aria-hidden="true"></i></div>
                <div>
                    <span>Total deliveries</span>
                    <strong>{{ number_format($allTimeCount) }}</strong>
                    <small>Qualifying completed deliveries.</small>
                </div>
            </article>
        </div>

        <section class="earnings-chart-card" aria-labelledby="trendTitle">
            <div class="delivery-section-head">
                <div>
                    <span class="section-kicker">Trend</span>
                    <h2 id="trendTitle">Earnings over {{ strtolower($periodLabel) }}</h2>
                </div>
                <span class="delivery-status is-assigned">{{ $periodCount }} {{ Str::plural('delivery', $periodCount) }}</span>
            </div>
            <div class="earnings-chart-wrap">
                <canvas id="earningsTrendChart" height="180" aria-label="Earnings trend chart"></canvas>
            </div>
            @if ($periodCount === 0)
                <p class="earnings-chart-empty">No completed deliveries in this period. Completed deliveries will appear here.</p>
            @endif
        </section>

        <section class="earnings-transactions-card" aria-labelledby="transactionsTitle">
            <div class="delivery-section-head">
                <div>
                    <span class="section-kicker">Transactions</span>
                    <h2 id="transactionsTitle">Earnings activity</h2>
                </div>
                <a href="{{ route('rider.earnings', array_merge(request()->query(), ['type' => 'all'])) }}" class="delivery-btn delivery-btn-ghost">Reset type</a>
            </div>

            <div class="earnings-type-tabs" role="tablist" aria-label="Transaction type">
                @foreach ($typeLabels as $key => $label)
                    <a href="{{ route('rider.earnings', array_merge(request()->except('page'), ['type' => $key])) }}"
                       class="earnings-type-tab {{ $type === $key ? 'is-active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="earnings-transaction-list">
                @forelse ($transactions as $order)
                    @php
                        $transactionDate = $order->delivered_at ?: $order->updated_at;
                        $transactionRef = $order->logistics_tracking_id ?: ('DEL-'.$order->id);
                        $counterparty = $order->vendor?->shop_name ?: ($order->vendor?->name ?: 'Juvo delivery');
                    @endphp
                    <button type="button"
                            class="earnings-transaction-row"
                            data-transaction-open
                            data-ref="{{ $transactionRef }}"
                            data-date="{{ $transactionDate?->format('d M Y, H:i') }}"
                            data-description="Delivery earnings for order #{{ $order->id }}"
                            data-counterparty="{{ $counterparty }}"
                            data-amount="{{ $currency }} {{ number_format((float) $order->delivery_fee, 0) }}"
                            data-status="Available"
                            data-order-url="{{ route('rider.deliveries.show', $order) }}">
                        <span class="earnings-transaction-icon is-credit"><i class="fas fa-arrow-down" aria-hidden="true"></i></span>
                        <span class="earnings-transaction-copy">
                            <strong>Delivery earning</strong>
                            <span>{{ $transactionRef }} · {{ $counterparty }}</span>
                            <small>{{ $transactionDate?->format('d M Y, H:i') }}</small>
                        </span>
                        <span class="earnings-transaction-amount is-credit">+{{ $currency }} {{ number_format((float) $order->delivery_fee, 0) }}</span>
                        <span class="earnings-transaction-status">Available</span>
                    </button>
                @empty
                    <div class="deliveries-empty">
                        <span class="deliveries-empty-icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                        <h3>No transactions in this period</h3>
                        <p>
                            @if ($type !== 'all' && $type !== 'delivery_earnings')
                                {{ $typeLabels[$type] }} are not recorded in the rider workspace yet.
                            @else
                                Completed delivery earnings will appear here.
                            @endif
                        </p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="earnings-payouts-card" aria-labelledby="payoutsTitle">
            <div class="delivery-section-head">
                <div>
                    <span class="section-kicker">Payouts</span>
                    <h2 id="payoutsTitle">Settlement status</h2>
                </div>
                <span class="delivery-status is-pending">No payout requests</span>
            </div>

            <div class="earnings-payout-summary">
                <div>
                    <span>Available to withdraw</span>
                    <strong>{{ $currency }} {{ number_format($allTimeTotal, 0) }}</strong>
                </div>
                <div>
                    <span>Pending requests</span>
                    <strong>{{ $currency }} 0</strong>
                </div>
                <div>
                    <span>Completed payouts</span>
                    <strong>{{ $currency }} 0</strong>
                </div>
                <div>
                    <span>Failed / rejected</span>
                    <strong>{{ $currency }} 0</strong>
                </div>
            </div>

            <div class="delivery-inline-note">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <span>Payout initiation and settlement records are not exposed in the rider app. Earnings shown are derived from completed deliveries; contact Juvo support or dispatch for payout scheduling and settlement details.</span>
            </div>

            <div class="deliveries-empty">
                <span class="deliveries-empty-icon"><i class="fas fa-money-bill-transfer" aria-hidden="true"></i></span>
                <h3>No payout history available</h3>
                <p>The rider workspace does not contain withdrawal requests, payout destinations or settlement records.</p>
            </div>
        </section>

        @if ($supportPhone)
            <p class="delivery-support-line">
                Need help with earnings? <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}">Call Juvo support</a>
            </p>
        @endif
    </section>

    <div class="modal fade" id="transactionDetailModal" tabindex="-1" aria-labelledby="transactionDetailTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content deliveries-modal">
                <div class="modal-body">
                    <span class="deliveries-modal-icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                    <h2 id="transactionDetailTitle">Transaction details</h2>
                    <div class="delivery-overview-grid">
                        <div><span>Reference</span><strong data-tx-ref>—</strong></div>
                        <div><span>Date</span><strong data-tx-date>—</strong></div>
                        <div><span>Counterparty</span><strong data-tx-counterparty>—</strong></div>
                        <div><span>Status</span><strong data-tx-status>—</strong></div>
                        <div class="is-wide"><span>Description</span><strong data-tx-description>—</strong></div>
                        <div class="is-wide"><span>Amount</span><strong data-tx-amount>—</strong></div>
                    </div>
                    <div class="deliveries-modal-actions">
                        <button type="button" class="delivery-btn delivery-btn-ghost" data-bs-dismiss="modal">Close</button>
                        <a href="#" class="delivery-btn delivery-btn-primary" data-tx-open-delivery>Open delivery <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" crossorigin=""></script>
<script>
(function () {
    const chartLabels = @json($chartLabels);
    const chartValues = @json($chartValues);

    const canvas = document.getElementById('earningsTrendChart');
    if (canvas && window.Chart) {
        const context = canvas.getContext('2d');
        const gradient = context.createLinearGradient(0, 0, 0, 180);
        gradient.addColorStop(0, 'rgba(255, 133, 15, 0.28)');
        gradient.addColorStop(1, 'rgba(255, 133, 15, 0)');

        new Chart(context, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Earnings (TZS)',
                    data: chartValues,
                    borderColor: '#ff850f',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: '#ff850f',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (item) => 'TZS ' + Number(item.raw || 0).toLocaleString(),
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: '#687386', font: { size: 10, weight: '700' }, maxRotation: 0 },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#687386',
                            font: { size: 10, weight: '700' },
                            callback: (value) => Number(value).toLocaleString(),
                        },
                        grid: { color: 'rgba(228, 233, 241, 0.7)' },
                    },
                },
            },
        });
    }

    const rangeInput = document.querySelector('[data-range-input]');
    const typeInput = document.querySelector('[data-type-input]');
    const customRange = document.querySelector('[data-custom-range]');
    const form = document.querySelector('[data-earnings-filter]');

    document.querySelectorAll('[data-range]').forEach((button) => {
        button.addEventListener('click', () => {
            const selected = button.dataset.range;
            rangeInput.value = selected;

            document.querySelectorAll('[data-range]').forEach((item) => {
                item.classList.toggle('is-active', item === button);
                item.setAttribute('aria-selected', item === button ? 'true' : 'false');
            });

            if (customRange) {
                customRange.classList.toggle('is-open', selected === 'custom');
            }

            if (selected !== 'custom') {
                form.submit();
            }
        });
    });

    const modalEl = document.getElementById('transactionDetailModal');
    const modal = window.bootstrap && modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const setValue = (selector, value) => {
        const element = modalEl?.querySelector(selector);
        if (element) element.textContent = value || '—';
    };

    document.querySelectorAll('[data-transaction-open]').forEach((row) => {
        row.addEventListener('click', () => {
            setValue('[data-tx-ref]', row.dataset.ref);
            setValue('[data-tx-date]', row.dataset.date);
            setValue('[data-tx-counterparty]', row.dataset.counterparty);
            setValue('[data-tx-status]', row.dataset.status);
            setValue('[data-tx-description]', row.dataset.description);
            setValue('[data-tx-amount]', row.dataset.amount);

            const link = modalEl?.querySelector('[data-tx-open-delivery]');
            if (link) link.href = row.dataset.orderUrl || '#';

            modal?.show();
        });
    });

    document.getElementById('refreshDeliveries')?.addEventListener('click', () => window.location.reload());
})();
</script>
@endpush
