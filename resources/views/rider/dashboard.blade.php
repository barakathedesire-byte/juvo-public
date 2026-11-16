@extends('rider.layouts.app')

@php
    $riderBuilder = \App\Support\Rider\RiderBuilderContent::forDisplay();
@endphp

@section('title', 'Dashboard')
@section('body_class', 'rider-dashboard')

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $riderName = $rider->first_name ?: 'Rider';
    $initials = Str::upper(Str::substr($riderName, 0, 1).Str::substr($rider->last_name ?? '', 0, 1));
    $activeCount = (int) ($stats['assigned_orders'] ?? 0);
    $rating = (float) ($stats['rating'] ?? 0);
    $today = now()->format('D, j M');
    $currentStatus = $currentOrder?->logistics_status ?? 'assigned';
    $statusClass = match ($currentStatus) {
        'in_transit' => 'is-transit',
        'picked_up' => 'is-picked',
        'accepted' => 'is-accepted',
        default => 'is-assigned',
    };

    $customerName = null;
    $customerPhone = null;
    $dropoffAddress = '';
    $pickupAddress = '';
    $currentCod = 0;

    if ($currentOrder) {
        $customerName = $currentOrder->shippingAddress?->name
            ?? trim(($currentOrder->customer?->first_name ?? '').' '.($currentOrder->customer?->last_name ?? ''));
        $customerName = $customerName ?: 'Customer';
        $customerPhone = $currentOrder->shippingAddress?->phone ?? $currentOrder->customer?->phone;
        $dropoffAddress = trim(($currentOrder->shippingAddress?->address ?? '').' '.($currentOrder->shippingAddress?->city ?? ''));
        $pickupAddress = trim(($currentOrder->pickupLocation?->address ?? '').' '.($currentOrder->pickupLocation?->city ?? ''));
        $currentCod = $currentOrder->isCod() && ! $currentOrder->cod_collected_at
            ? (float) ($currentOrder->cod_amount ?: $currentOrder->total_amount)
            : 0;
    }
@endphp

@section('hero')
    <header class="rider-hero">
        <div class="rider-hero-inner">
            <div class="rider-hero-top">
                <a class="rider-hero-brand" href="{{ route('rider.dashboard') }}">
                    <span class="rider-hero-brand-mark"><i class="fas fa-motorcycle"></i></span>
                    <span class="rider-hero-brand-copy">
                        <strong>Juvo Rider</strong>
                        <span>Delivery partner</span>
                    </span>
                </a>

                <div class="rider-hero-actions">
                    <button type="button"
                            class="rider-icon-btn {{ $unreadCount > 0 ? 'has-unread' : '' }}"
                            id="notifBell"
                            aria-label="Notifications{{ $unreadCount > 0 ? ' ('.$unreadCount.' unread)' : '' }}"
                            aria-expanded="false"
                            aria-controls="notifPanel">
                        <i class="fas fa-bell"></i>
                        @if ($unreadCount > 0)
                            <span class="rider-icon-badge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        @endif
                    </button>
                    <a href="{{ route('rider.profile') }}" class="rider-hero-avatar" aria-label="Open profile">{{ $initials }}</a>
                </div>
            </div>

            <div class="rider-hero-body">
                <div class="rider-hero-greeting">
                    <p class="rider-hero-kicker">{{ $greeting }},</p>
                    <h1>{{ $riderName }}</h1>
                    <p class="rider-hero-sub">
                        <span>{{ $today }}</span>
                        <span class="rider-hero-sep" aria-hidden="true">&middot;</span>
                        <span>{{ $activeCount > 0 ? $activeCount.' active '.Str::plural('delivery', $activeCount) : 'No active deliveries' }}</span>
                    </p>
                </div>

                <div class="rider-availability" role="group" aria-labelledby="availabilityTitle">
                    <div class="rider-availability-copy">
                        <span class="rider-availability-dot {{ $rider->is_online ? 'online' : 'offline' }}" id="availabilityDot" aria-hidden="true"></span>
                        <div>
                            <strong id="availabilityTitle">{{ $rider->is_online ? "You're online" : "You're offline" }}</strong>
                            <span id="availabilityHint">{{ $rider->is_online ? 'Receiving delivery requests' : 'Go online to receive requests' }}</span>
                        </div>
                    </div>
                    <button type="button"
                            id="onlineToggle"
                            class="rider-switch {{ $rider->is_online ? 'is-on' : '' }}"
                            role="switch"
                            aria-checked="{{ $rider->is_online ? 'true' : 'false' }}"
                            aria-label="Toggle availability">
                        <span class="rider-switch-thumb" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>

        <svg class="rider-hero-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path d="M0,54 C180,92 360,94 540,66 C720,38 900,20 1080,40 C1260,60 1360,70 1440,58 L1440,90 L0,90 Z" fill="#f5f7fa" opacity=".38"/>
            <path d="M0,70 C240,98 480,98 720,70 C960,42 1200,42 1440,70 L1440,90 L0,90 Z" fill="#f5f7fa"/>
        </svg>

        <div class="rider-notif-panel" id="notifPanel" role="dialog" aria-modal="false" aria-labelledby="notifPanelTitle" hidden>
            <div class="rider-notif-head">
                <div>
                    <span class="rider-notif-kicker">Inbox</span>
                    <strong id="notifPanelTitle">Notifications</strong>
                </div>
                <button type="button" class="rider-notif-close" id="notifClose" aria-label="Close notifications">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div class="rider-notif-list">
                @forelse ($notifications as $notification)
                    @php
                        $notificationData = is_array($notification->data ?? null) ? $notification->data : [];
                    @endphp
                    <div class="rider-notif-item {{ $notification->read_at ? '' : 'is-unread' }}">
                        <span class="rider-notif-item-icon"><i class="fas fa-bell"></i></span>
                        <div class="rider-notif-item-copy">
                            <strong>{{ $notificationData['title'] ?? 'Delivery update' }}</strong>
                            @if (! empty($notificationData['message']))
                                <p>{{ $notificationData['message'] }}</p>
                            @endif
                            <span>{{ $notification->created_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="rider-notif-empty">
                        <span><i class="far fa-bell-slash"></i></span>
                        <strong>You're all caught up</strong>
                        <p>New delivery updates will appear here.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </header>

    @if($riderBuilder['features']['show_earnings'] ?? true)
    <div class="rider-hero-earnings">
        <section class="earnings-card" aria-labelledby="earningsTitle">
            <div class="earnings-head">
                <div>
                    <span class="earnings-label" id="earningsTitle">{{ $riderBuilder['dashboard_earnings_label'] ?: "Today's earnings" }}</span>
                    <div class="earnings-amount">{{ $currency }} {{ number_format((float) $stats['earnings_today'], 0) }}</div>
                    <p class="earnings-meta">
                        {{ (int) $stats['completed_today'] }} {{ Str::plural('delivery', (int) $stats['completed_today']) }} completed today
                    </p>
                </div>
                <a href="{{ route('rider.earnings') }}" class="earnings-link">
                    View earnings <span aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
                </a>
            </div>

            <div class="earnings-stats">
                <div class="earnings-stat">
                    <span>This week</span>
                    <strong>{{ $currency }} {{ number_format((float) $stats['earnings_week'], 0) }}</strong>
                </div>
                <div class="earnings-stat">
                    <span>All time</span>
                    <strong>{{ $currency }} {{ number_format((float) $stats['total_earnings'], 0) }}</strong>
                </div>
                <div class="earnings-stat">
                    <span>Rating</span>
                    <strong>
                        @if ($rating > 0)
                            {{ number_format($rating, 1) }}
                            <i class="fas fa-star earnings-star" aria-hidden="true"></i>
                        @else
                            &mdash;
                        @endif
                    </strong>
                </div>
            </div>

            @if ($cashToCollect > 0)
                <div class="cash-to-collect">
                    <span class="cash-to-collect-icon"><i class="fas fa-hand-holding-dollar"></i></span>
                    <div>
                        <strong>Cash to collect</strong>
                        <span>{{ $currency }} {{ number_format((float) $cashToCollect, 0) }} <small>across active cash orders</small></span>
                    </div>
                </div>
            @endif
        </section>
    </div>
    @endif
@endsection

@section('content')
    <section class="assignment-card {{ $currentOrder ? '' : 'is-empty' }}" aria-labelledby="assignmentTitle">
        <div class="assignment-head">
            <div>
                <span class="section-kicker" id="assignmentTitle">Current assignment</span>
                <h2>{{ $currentOrder ? 'Order #'.$currentOrder->id : 'No active delivery' }}</h2>
            </div>
            @if ($currentOrder)
                <span class="status-pill {{ $statusClass }}">{{ $currentOrder->logisticsStatusLabel() }}</span>
            @endif
        </div>

        @if ($currentOrder)
            <div class="route-list">
                <div class="route-stop">
                    <span class="route-dot is-pickup"><i class="fas fa-store"></i></span>
                    <div class="route-copy">
                        <span class="route-label">Pickup</span>
                        <strong>{{ $currentOrder->pickupLocation?->name ?? 'Pickup point' }}</strong>
                        <small>{{ $pickupAddress !== '' ? $pickupAddress : 'Address not provided' }}</small>
                    </div>
                </div>
                <div class="route-stop">
                    <span class="route-dot is-dropoff"><i class="fas fa-location-dot"></i></span>
                    <div class="route-copy">
                        <span class="route-label">Drop-off</span>
                        <strong>{{ $currentOrder->shippingAddress?->name ?? $customerName }}</strong>
                        <small>{{ $dropoffAddress !== '' ? $dropoffAddress : 'Address not provided' }}</small>
                    </div>
                </div>
            </div>

            <div class="assignment-customer">
                <span class="assignment-avatar">{{ $initials }}</span>
                <div class="assignment-customer-copy">
                    <strong>{{ $customerName }}</strong>
                    <span>{{ $customerPhone ?: 'Phone not provided' }}</span>
                </div>
                @if ($customerPhone)
                    <a href="tel:{{ $customerPhone }}" class="assignment-call" aria-label="Call {{ $customerName }}">
                        <i class="fas fa-phone"></i>
                    </a>
                @endif
            </div>

            <div class="assignment-foot">
                <div class="assignment-fee">
                    <span>Delivery fee</span>
                    <strong>{{ $currentOrder->currency ?? $currency }} {{ number_format((float) $currentOrder->delivery_fee, 0) }}</strong>
                </div>
                <div class="assignment-actions">
                    @if ($currentCod > 0)
                        <span class="cod-chip"><i class="fas fa-money-bill-wave"></i> Collect {{ $currency }} {{ number_format($currentCod, 0) }}</span>
                    @endif
                    <a href="{{ route('rider.deliveries.show', $currentOrder) }}" class="btn btn-juvo assignment-cta">
                        Open delivery <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        @else
            <div class="assignment-empty">
                <span class="assignment-empty-icon"><i class="fas fa-box-open"></i></span>
                <p id="emptyAssignmentCopy">{{ $riderBuilder['empty_assignment_copy'] }}</p>
                <div class="assignment-empty-actions">
                    <a href="{{ route('rider.orders') }}" class="btn btn-juvo">View deliveries</a>
                    @if (! $rider->is_online)
                        <button type="button" class="btn btn-outline-secondary" data-go-online>Go online</button>
                    @endif
                </div>
            </div>
        @endif
    </section>

    @php
        $defaultActivity = $upcomingOrders->isNotEmpty() || $recentCompleted->isEmpty() ? 'upcoming' : 'recent';
    @endphp

    <div class="dashboard-activity">
        <div class="activity-tabs" role="tablist" aria-label="Delivery activity">
            <button type="button"
                    class="activity-tab {{ $defaultActivity === 'upcoming' ? 'is-active' : '' }}"
                    id="upcomingTab"
                    role="tab"
                    aria-selected="{{ $defaultActivity === 'upcoming' ? 'true' : 'false' }}"
                    aria-controls="upcomingPanel"
                    tabindex="{{ $defaultActivity === 'upcoming' ? '0' : '-1' }}"
                    data-activity-tab="upcoming">
                <span>Upcoming</span>
                <b>{{ $upcomingOrders->count() }}</b>
            </button>
            <button type="button"
                    class="activity-tab {{ $defaultActivity === 'recent' ? 'is-active' : '' }}"
                    id="recentTab"
                    role="tab"
                    aria-selected="{{ $defaultActivity === 'recent' ? 'true' : 'false' }}"
                    aria-controls="recentPanel"
                    tabindex="{{ $defaultActivity === 'recent' ? '0' : '-1' }}"
                    data-activity-tab="recent">
                <span>Recent</span>
                <b>{{ $recentCompleted->count() }}</b>
            </button>
        </div>

        <div class="dashboard-grid">
        <section class="panel-card {{ $defaultActivity === 'upcoming' ? 'is-active' : '' }}"
                 id="upcomingPanel"
                 role="tabpanel"
                 aria-labelledby="upcomingTab"
                 tabindex="0"
                 data-activity-panel="upcoming">
            <div class="panel-head">
                <div>
                    <span class="section-kicker" id="upcomingTitle">Upcoming stops</span>
                    <h2>{{ $upcomingOrders->count() }} {{ Str::plural('delivery', $upcomingOrders->count()) }}</h2>
                </div>
                <a href="{{ route('rider.orders') }}" class="panel-link">View all</a>
            </div>
            <div class="panel-list">
                @forelse ($upcomingOrders as $order)
                    <a href="{{ route('rider.deliveries.show', $order) }}" class="delivery-row">
                        <span class="delivery-row-icon"><i class="fas fa-box"></i></span>
                        <span class="delivery-row-copy">
                            <strong>#{{ $order->id }} &middot; {{ $order->shippingAddress?->city ?? 'Drop-off' }}</strong>
                            <span>{{ $order->pickupLocation?->name ?? 'Pickup' }} &rarr; {{ $order->shippingAddress?->address ?? 'Customer address' }}</span>
                        </span>
                        <span class="delivery-row-meta">
                            <span class="status-dot-label">{{ $order->logisticsStatusLabel() }}</span>
                            <strong>{{ $order->currency ?? $currency }} {{ number_format((float) $order->delivery_fee, 0) }}</strong>
                        </span>
                        <i class="fas fa-chevron-right delivery-row-chevron" aria-hidden="true"></i>
                    </a>
                @empty
                    <div class="panel-empty">
                        <i class="fas fa-route"></i>
                        <p>No upcoming stops. New assignments will show up here.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="panel-card {{ $defaultActivity === 'recent' ? 'is-active' : '' }}"
                 id="recentPanel"
                 role="tabpanel"
                 aria-labelledby="recentTab"
                 tabindex="0"
                 data-activity-panel="recent">
            <div class="panel-head">
                <div>
                    <span class="section-kicker" id="recentTitle">Recent deliveries</span>
                    <h2>{{ $recentCompleted->count() }} completed</h2>
                </div>
                <a href="{{ route('rider.orders') }}" class="panel-link">View all</a>
            </div>
            <div class="panel-list">
                @forelse ($recentCompleted as $order)
                    <a href="{{ route('rider.deliveries.show', $order) }}" class="delivery-row">
                        <span class="delivery-row-icon is-success"><i class="fas fa-check"></i></span>
                        <span class="delivery-row-copy">
                            <strong>#{{ $order->id }}</strong>
                            <span>{{ $order->delivered_at?->diffForHumans() ?? 'Delivered' }}</span>
                        </span>
                        <span class="delivery-row-meta">
                            <strong class="is-positive">{{ $order->currency ?? $currency }} {{ number_format((float) $order->delivery_fee, 0) }}</strong>
                        </span>
                    </a>
                @empty
                    <div class="panel-empty">
                        <i class="fas fa-receipt"></i>
                        <p>No completed deliveries yet.</p>
                    </div>
                @endforelse
            </div>
        </section>
        </div>
    </div>

    <div class="rider-toast" id="riderToast" role="status" aria-live="polite" hidden>
        <i class="fas fa-check-circle" aria-hidden="true"></i>
        <span></span>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const toggle = document.getElementById('onlineToggle');
        const statusTitle = document.getElementById('availabilityTitle');
        const statusHint = document.getElementById('availabilityHint');
        const statusDot = document.getElementById('availabilityDot');
        const toast = document.getElementById('riderToast');
        const bell = document.getElementById('notifBell');
        const panel = document.getElementById('notifPanel');
        const closeBtn = document.getElementById('notifClose');
        const emptyCopy = document.getElementById('emptyAssignmentCopy');
        let toastTimer = null;

        function showToast(message, type) {
            if (!toast) return;

            toast.classList.toggle('is-error', type === 'error');
            const icon = toast.querySelector('i');
            if (icon) icon.className = type === 'error' ? 'fas fa-circle-exclamation' : 'fas fa-check-circle';
            const copy = toast.querySelector('span');
            if (copy) copy.textContent = message;
            toast.hidden = false;

            window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(() => { toast.hidden = true; }, 3400);
        }

        function requestLocation() {
            if (!navigator.geolocation) return;

            navigator.geolocation.getCurrentPosition((position) => {
                fetch('{{ route('rider.update-location') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    }),
                }).catch(() => {});
            }, () => {});
        }

        if (toggle) {
            if (toggle.classList.contains('is-on')) requestLocation();

            toggle.addEventListener('click', async () => {
                if (toggle.disabled) return;

                toggle.disabled = true;
                toggle.classList.add('is-loading');
                toggle.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch('{{ route('rider.toggle-online') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || !data.success) {
                        showToast(data.message || 'Could not update availability. Please try again.', 'error');
                        return;
                    }

                    const online = Boolean(data.is_online);
                    toggle.classList.toggle('is-on', online);
                    toggle.setAttribute('aria-checked', online ? 'true' : 'false');

                    if (statusDot) {
                        statusDot.classList.toggle('online', online);
                        statusDot.classList.toggle('offline', !online);
                    }
                    if (statusTitle) statusTitle.textContent = online ? "You're online" : "You're offline";
                    if (statusHint) statusHint.textContent = online ? 'Receiving delivery requests' : 'Go online to receive requests';
                    document.querySelectorAll('[data-go-online]').forEach((button) => {
                        button.hidden = online;
                    });

                    showToast(data.message || (online ? 'You are now online' : 'You are now offline'), 'success');
                    if (online) requestLocation();
                } catch (error) {
                    showToast('Connection problem. Availability was not changed.', 'error');
                } finally {
                    toggle.disabled = false;
                    toggle.classList.remove('is-loading');
                    toggle.removeAttribute('aria-busy');
                }
            });

            document.querySelectorAll('[data-go-online]').forEach((button) => {
                button.addEventListener('click', () => toggle.click());
            });
        }

        async function markNotificationsRead() {
            if (!bell) return;
            const badge = bell.querySelector('.rider-icon-badge');
            if (!badge) return;

            try {
                const response = await fetch('{{ route('rider.notifications.read') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (!response.ok) return;

                badge.remove();
                bell.classList.remove('has-unread');
                panel?.querySelectorAll('.rider-notif-item.is-unread').forEach((item) => {
                    item.classList.remove('is-unread');
                });
            } catch (error) {
                // Keep the unread badge if the request could not be completed.
            }
        }

        function setNotifications(open) {
            if (!panel || !bell) return;

            panel.hidden = !open;
            bell.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                markNotificationsRead();
                closeBtn?.focus();
            }
        }

        bell?.addEventListener('click', (event) => {
            event.stopPropagation();
            setNotifications(panel ? panel.hidden : false);
        });

        closeBtn?.addEventListener('click', () => setNotifications(false));

        document.addEventListener('click', (event) => {
            if (!panel || panel.hidden) return;
            if (!panel.contains(event.target) && !bell?.contains(event.target)) {
                setNotifications(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setNotifications(false);
        });

        if (window.location.hash === '#notifications') setNotifications(true);

        const activityTabs = Array.from(document.querySelectorAll('[data-activity-tab]'));
        const activityPanels = Array.from(document.querySelectorAll('[data-activity-panel]'));

        function activateActivity(key) {
            activityTabs.forEach((tab) => {
                const active = tab.dataset.activityTab === key;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
            });
            activityPanels.forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.activityPanel === key);
            });
        }

        activityTabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activateActivity(tab.dataset.activityTab));
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

                event.preventDefault();
                let nextIndex = index;
                if (event.key === 'ArrowRight') nextIndex = (index + 1) % activityTabs.length;
                if (event.key === 'ArrowLeft') nextIndex = (index - 1 + activityTabs.length) % activityTabs.length;
                if (event.key === 'Home') nextIndex = 0;
                if (event.key === 'End') nextIndex = activityTabs.length - 1;

                const next = activityTabs[nextIndex];
                next.focus();
                activateActivity(next.dataset.activityTab);
            });
        });
    })();
</script>
@endpush
