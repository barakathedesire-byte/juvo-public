@extends('rider.layouts.app')

@section('title', 'Delivery #'.$order->id)
@section('body_class', 'rider-deliveries')

@php
    $status = $order->logistics_status ?? 'pending';
    $currency = $order->currency ?: getWebConfig('default_currency', 'TZS');
    $events = collect($order->deliveryEvents ?? []);
    $eventAt = fn (string $type) => $events->firstWhere('type', $type)?->created_at;
    $arrivedPickupAt = $eventAt('arrived_pickup');
    $arrivedDropoffAt = $eventAt('arrived_dropoff');
    $inTransitAt = $eventAt('in_transit');

    $pickupName = $order->vendor?->shop_name ?: ($order->vendor?->name ?: ($order->pickupLocation?->name ?? 'Pickup point'));
    $pickupAddress = trim((string) ($order->vendor?->address ?? ''));
    $pickupPhone = $order->vendor?->phone;
    $pickupLat = $order->vendor?->latitude;
    $pickupLng = $order->vendor?->longitude;

    $dropoffName = $order->shippingAddress?->name ?: ($order->customer?->name ?: 'Customer');
    $dropoffAddress = trim(implode(', ', array_filter([
        $order->shippingAddress?->address,
        $order->shippingAddress?->city,
        $order->shippingAddress?->state,
        $order->shippingAddress?->postal_code,
        $order->shippingAddress?->country,
    ])));
    $dropoffPhone = $order->shippingAddress?->phone ?: $order->customer?->phone;
    $dropoffLat = $order->shippingAddress?->latitude;
    $dropoffLng = $order->shippingAddress?->longitude;

    $itemCount = (int) $order->details->sum('quantity');
    $isCod = $order->isCod();
    $codDue = $isCod && ! $order->cod_collected_at && $order->payment_status !== 'paid'
        ? (float) ($order->cod_amount ?: $order->total_amount)
        : 0.0;

    $pickupPhase = in_array($status, ['assigned', 'accepted'], true);
    $navName = $pickupPhase ? $pickupName : $dropoffName;
    $navAddress = $pickupPhase ? $pickupAddress : $dropoffAddress;
    $navLat = $pickupPhase ? $pickupLat : $dropoffLat;
    $navLng = $pickupPhase ? $pickupLng : $dropoffLng;
    $navQuery = $navLat !== null && $navLng !== null && $navLat !== '' && $navLng !== ''
        ? $navLat.','.$navLng
        : $navAddress;
    $hasNavigation = $navQuery !== '';
    $navUrl = 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($navQuery).'&travelmode=driving';
    $mapLat = $navLat !== null && $navLat !== '' ? (float) $navLat : null;
    $mapLng = $navLng !== null && $navLng !== '' ? (float) $navLng : null;

    $canAccept = $status === 'assigned';
    $canArrivePickup = $status === 'accepted' && ! $arrivedPickupAt;
    $canConfirmPickup = $status === 'accepted' && (bool) $arrivedPickupAt;
    $canStartTransit = $status === 'picked_up';
    $canArriveDropoff = $status === 'in_transit' && ! $arrivedDropoffAt;
    $canComplete = $status === 'in_transit' && (bool) $arrivedDropoffAt;
    $canReport = in_array($status, ['accepted', 'picked_up', 'in_transit'], true);
    $isClosed = in_array($status, ['delivered', 'failed', 'returned', 'cancelled'], true);

    $statusClass = match ($status) {
        'assigned' => 'is-new',
        'accepted' => 'is-assigned',
        'picked_up' => 'is-picked',
        'in_transit' => 'is-transit',
        'delivered' => 'is-done',
        'failed', 'returned', 'cancelled' => 'is-issue',
        default => 'is-pending',
    };

    $journey = [
        ['key' => 'accepted', 'label' => 'Accepted', 'at' => $order->accepted_at ?: $eventAt('accepted'), 'done' => (bool) $order->accepted_at],
        ['key' => 'arrived_pickup', 'label' => 'Arrived at pickup', 'at' => $arrivedPickupAt, 'done' => (bool) $arrivedPickupAt],
        ['key' => 'picked_up', 'label' => 'Picked up', 'at' => $order->picked_up_at ?: $eventAt('picked_up'), 'done' => (bool) $order->picked_up_at],
        ['key' => 'in_transit', 'label' => 'In transit', 'at' => $inTransitAt, 'done' => in_array($status, ['in_transit', 'delivered'], true)],
        ['key' => 'arrived_dropoff', 'label' => 'Arrived at drop-off', 'at' => $arrivedDropoffAt, 'done' => (bool) $arrivedDropoffAt],
        ['key' => 'delivered', 'label' => 'Delivered', 'at' => $order->delivered_at, 'done' => $status === 'delivered'],
    ];

    $currentMarked = false;
    foreach ($journey as $index => $step) {
        $journey[$index]['current'] = false;

        if (! $step['done'] && ! $isClosed && ! $currentMarked) {
            $journey[$index]['current'] = true;
            $currentMarked = true;
        }
    }
@endphp

@section('hero')
    @include('rider.partials.deliveries-header', [
        'title' => 'Delivery #'.$order->id,
        'subtitle' => $order->logisticsStatusLabel().' • '.($pickupName.' to '.$dropoffName),
        'eyebrow' => 'Delivery details',
        'backUrl' => route('rider.orders'),
        'unreadCount' => $unreadCount,
    ])
@endsection

@section('content')
    <div class="delivery-detail">
        <section class="delivery-overview">
            <div class="delivery-overview-top">
                <div>
                    <span class="delivery-card-kicker">Order reference</span>
                    <h2>#{{ $order->id }}</h2>
                    @if ($order->logistics_tracking_id)
                        <p class="delivery-tracking">{{ $order->logistics_tracking_id }}</p>
                    @endif
                </div>
                <span class="delivery-status {{ $statusClass }}">{{ $order->logisticsStatusLabel() }}</span>
            </div>

            <div class="delivery-overview-grid">
                <div>
                    <span>Delivery fee</span>
                    <strong>{{ $currency }} {{ number_format((float) $order->delivery_fee, 0) }}</strong>
                </div>
                <div>
                    <span>Items</span>
                    <strong>{{ $itemCount > 0 ? $itemCount : '—' }}</strong>
                </div>
                <div>
                    <span>Placed</span>
                    <strong>{{ $order->created_at?->format('d M, H:i') ?? '—' }}</strong>
                </div>
                @if ($isCod)
                    <div>
                        <span>Cash on delivery</span>
                        <strong>{{ $codDue > 0 ? $currency.' '.number_format($codDue, 0) : 'Collected' }}</strong>
                    </div>
                @endif
            </div>

            @if ($codDue > 0)
                <div class="delivery-cod-alert">
                    <i class="fas fa-hand-holding-dollar" aria-hidden="true"></i>
                    <div>
                        <strong>Collect cash on delivery</strong>
                        <span>{{ $currency }} {{ number_format($codDue, 0) }} is due from the receiver before completion.</span>
                    </div>
                </div>
            @endif
        </section>

        @if (! $hasNavigation)
            <div class="delivery-inline-note">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <span>A complete address is not available for this stage. Check the route details below.</span>
            </div>
        @else
            <section class="delivery-map-card">
                <div class="delivery-section-head">
                    <div>
                        <span class="section-kicker">Route</span>
                        <h2>{{ $pickupPhase ? 'To pickup' : 'To customer' }}</h2>
                    </div>
                    <a href="{{ $navUrl }}" class="delivery-btn delivery-btn-primary" target="_blank" rel="noopener" data-navigate-link data-destination="{{ $navQuery }}">
                        <i class="fas fa-diamond-turn-right" aria-hidden="true"></i> Navigate
                    </a>
                </div>

                <div class="delivery-map-preview"
                     id="deliveryMapPreview"
                     data-map-lat="{{ $mapLat }}"
                     data-map-lng="{{ $mapLng }}"
                     data-map-address="{{ $navAddress }}"
                     data-map-label="{{ $navName }}"
                     role="img"
                     aria-label="Map preview for {{ $navName }}"></div>

                <div class="delivery-map-foot">
                    <p id="mapPreviewNote">Preparing map for {{ $navName }}.</p>
                    <button type="button" class="delivery-btn delivery-btn-ghost" data-bs-toggle="modal" data-bs-target="#deliveryMapModal">
                        <i class="fas fa-expand" aria-hidden="true"></i> Full map
                    </button>
                </div>
            </section>
        @endif

        <section class="delivery-stops" aria-label="Pickup and drop-off details">
            <article class="delivery-stop-card is-pickup">
                <div class="delivery-stop-head">
                    <span class="delivery-route-dot is-pickup"><i class="fas fa-store" aria-hidden="true"></i></span>
                    <div>
                        <span>Pickup</span>
                        <h2>{{ $pickupName }}</h2>
                    </div>
                    @if (in_array($status, ['picked_up', 'in_transit', 'delivered'], true))
                        <span class="delivery-stop-done"><i class="fas fa-check" aria-hidden="true"></i> Collected</span>
                    @endif
                </div>
                <p class="delivery-stop-address">{{ $pickupAddress !== '' ? $pickupAddress : 'Pickup address not provided' }}</p>
                @if ($pickupPhone)
                    @php $pickupTel = preg_replace('/[^0-9+]/', '', $pickupPhone); @endphp
                    <a href="tel:{{ $pickupTel }}" class="delivery-contact-link">
                        <i class="fas fa-phone" aria-hidden="true"></i> Call store
                    </a>
                @endif
            </article>

            <article class="delivery-stop-card is-dropoff">
                <div class="delivery-stop-head">
                    <span class="delivery-route-dot is-dropoff"><i class="fas fa-location-dot" aria-hidden="true"></i></span>
                    <div>
                        <span>Drop-off</span>
                        <h2>{{ $dropoffName }}</h2>
                    </div>
                    @if ($status === 'delivered')
                        <span class="delivery-stop-done"><i class="fas fa-check" aria-hidden="true"></i> Delivered</span>
                    @endif
                </div>
                <p class="delivery-stop-address">{{ $dropoffAddress !== '' ? $dropoffAddress : 'Drop-off address not provided' }}</p>
                @if ($dropoffPhone)
                    @php $dropoffTel = preg_replace('/[^0-9+]/', '', $dropoffPhone); @endphp
                    <a href="tel:{{ $dropoffTel }}" class="delivery-contact-link">
                        <i class="fas fa-phone" aria-hidden="true"></i> Call customer
                    </a>
                @endif
            </article>
        </section>

        @if ($order->note)
            <section class="delivery-note-card">
                <div class="delivery-section-head">
                    <div>
                        <span class="section-kicker">Instructions</span>
                        <h2>Customer note</h2>
                    </div>
                </div>
                <p>{{ $order->note }}</p>
            </section>
        @endif

        <section class="delivery-progress-card">
            <div class="delivery-section-head">
                <div>
                    <span class="section-kicker">Progress</span>
                    <h2>Delivery journey</h2>
                </div>
                @if ($status === 'delivered')
                    <span class="delivery-status is-done">Completed</span>
                @endif
            </div>

            <ol class="delivery-timeline">
                @foreach ($journey as $step)
                    <li class="delivery-timeline-step {{ $step['done'] ? 'is-done' : '' }} {{ $step['current'] ? 'is-current' : '' }}">
                        <span class="delivery-timeline-dot">
                            @if ($step['done'])
                                <i class="fas fa-check" aria-hidden="true"></i>
                            @elseif ($step['current'])
                                <i class="fas fa-circle" aria-hidden="true"></i>
                            @else
                                <i class="far fa-circle" aria-hidden="true"></i>
                            @endif
                        </span>
                        <div>
                            <strong>{{ $step['label'] }}</strong>
                            <span>{{ $step['at'] ? $step['at']->format('d M, H:i') : ($step['current'] ? 'Next step' : 'Pending') }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        @if ($status === 'failed' || $status === 'returned' || $status === 'cancelled')
            <section class="delivery-issue-card">
                <span class="deliveries-empty-icon"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></span>
                <div>
                    <h2>{{ $order->logisticsStatusLabel() }}</h2>
                    <p>{{ $order->failure_note ?: ($order->cancel_reason ?: 'This delivery is no longer active. Contact dispatch if you need help.') }}</p>
                    @if ($order->failed_reason)
                        <span class="delivery-issue-reason">{{ \App\Models\Order::FAILURE_REASONS[$order->failed_reason] ?? ucwords(str_replace('_', ' ', $order->failed_reason)) }}</span>
                    @endif
                </div>
            </section>
        @endif

        @if ($status === 'delivered')
            <section class="delivery-complete-card">
                <div class="delivery-section-head">
                    <div>
                        <span class="section-kicker">Proof of delivery</span>
                        <h2>Delivery completed</h2>
                    </div>
                    <span class="delivery-status is-done">Delivered</span>
                </div>
                <div class="delivery-overview-grid">
                    <div><span>Received by</span><strong>{{ $order->pod_receiver_name ?: '—' }}</strong></div>
                    <div><span>Completed</span><strong>{{ $order->delivered_at?->format('d M, H:i') ?? '—' }}</strong></div>
                    @if ($order->pod_notes)
                        <div class="is-wide"><span>Notes</span><strong>{{ $order->pod_notes }}</strong></div>
                    @endif
                </div>
                <a href="{{ route('rider.orders') }}" class="delivery-btn delivery-btn-primary">
                    Back to deliveries <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </section>
        @elseif (! $isClosed)
            <section class="delivery-action-card" aria-labelledby="nextActionTitle">
                <div class="delivery-section-head">
                    <div>
                        <span class="section-kicker">Next action</span>
                        <h2 id="nextActionTitle">
                            @if ($canAccept)
                                Accept this delivery
                            @elseif ($canArrivePickup)
                                Head to the pickup
                            @elseif ($canConfirmPickup)
                                Confirm the pickup
                            @elseif ($canStartTransit)
                                Start the delivery
                            @elseif ($canArriveDropoff)
                                Head to the customer
                            @elseif ($canComplete)
                                Complete the delivery
                            @endif
                        </h2>
                    </div>
                    @if ($canReport)
                        <button type="button" class="delivery-btn delivery-btn-danger-ghost" data-bs-toggle="modal" data-bs-target="#reportProblemModal">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Report problem
                        </button>
                    @endif
                </div>

                <div class="delivery-action-stack">
                    @if ($canAccept)
                        <p class="delivery-action-copy">Accepting confirms that you can collect order #{{ $order->id }} and deliver it to {{ $dropoffName }}.</p>
                        <form method="POST"
                              action="{{ route('rider.deliveries.accept', $order) }}"
                              class="delivery-accept-form"
                              data-order-ref="{{ $order->id }}"
                              data-order-route="{{ $pickupName }} to {{ $dropoffName }}">
                            @csrf
                            <button type="submit" class="delivery-btn delivery-btn-primary delivery-btn-block" data-accept-button>
                                Accept delivery <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endif

                    @if ($canArrivePickup)
                        <div class="delivery-action-buttons">
                            @if ($hasNavigation)
                                <a href="{{ $navUrl }}" class="delivery-btn delivery-btn-primary" target="_blank" rel="noopener" data-navigate-link data-destination="{{ $navQuery }}">
                                    <i class="fas fa-diamond-turn-right" aria-hidden="true"></i> Navigate to pickup
                                </a>
                            @endif
                            <form method="POST" action="{{ route('rider.deliveries.arrived-pickup', $order) }}" data-delivery-action>
                                @csrf
                                <button type="submit" class="delivery-btn delivery-btn-ghost">
                                    <i class="fas fa-location-arrow" aria-hidden="true"></i> I have arrived
                                </button>
                            </form>
                        </div>
                    @endif

                    @if ($canConfirmPickup)
                        <p class="delivery-action-copy">Confirm that you have collected the order from {{ $pickupName }}.</p>
                        <form method="POST" action="{{ route('rider.deliveries.pickup', $order) }}" data-delivery-action>
                            @csrf
                            <button type="submit" class="delivery-btn delivery-btn-primary delivery-btn-block">
                                Confirm pickup <i class="fas fa-box" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endif

                    @if ($canStartTransit)
                        <p class="delivery-action-copy">Start the delivery when you are ready to leave {{ $pickupName }}.</p>
                        <form method="POST" action="{{ route('rider.deliveries.transit', $order) }}" data-delivery-action>
                            @csrf
                            <button type="submit" class="delivery-btn delivery-btn-primary delivery-btn-block">
                                Start delivery <i class="fas fa-motorcycle" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endif

                    @if ($canArriveDropoff)
                        <div class="delivery-action-buttons">
                            @if ($hasNavigation)
                                <a href="{{ $navUrl }}" class="delivery-btn delivery-btn-primary" target="_blank" rel="noopener" data-navigate-link data-destination="{{ $navQuery }}">
                                    <i class="fas fa-diamond-turn-right" aria-hidden="true"></i> Navigate to customer
                                </a>
                            @endif
                            <form method="POST" action="{{ route('rider.deliveries.arrived-dropoff', $order) }}" data-delivery-action>
                                @csrf
                                <button type="submit" class="delivery-btn delivery-btn-ghost">
                                    <i class="fas fa-location-arrow" aria-hidden="true"></i> I have arrived
                                </button>
                            </form>
                        </div>
                    @endif

                    @if ($canComplete)
                        <p class="delivery-action-copy">Record who received this delivery. Add a photo or note where needed.</p>

                        <form id="deliveryCompleteForm"
                              method="POST"
                              action="{{ route('rider.deliveries.deliver', $order) }}"
                              enctype="multipart/form-data"
                              class="delivery-complete-form"
                              data-delivery-action>
                            @csrf
                            <div class="delivery-field">
                                <label for="receiver_name">Received by</label>
                                <input type="text" id="receiver_name" name="receiver_name" value="{{ old('receiver_name', $dropoffName) }}" maxlength="120" required autocomplete="name">
                            </div>

                            <div class="delivery-field">
                                <label for="delivery_notes">Delivery notes <span>Optional</span></label>
                                <textarea id="delivery_notes" name="notes" rows="2" maxlength="1000" placeholder="Anything dispatch should know">{{ old('notes') }}</textarea>
                            </div>

                            <div class="delivery-field">
                                <label for="delivery_photo">Proof photo <span>Optional</span></label>
                                <input type="file" id="delivery_photo" name="photo" accept="image/*" capture="environment">
                            </div>

                            @if ($codDue > 0)
                                <label class="delivery-check">
                                    <input type="checkbox" name="cod_collected" value="1" required>
                                    <span>I collected {{ $currency }} {{ number_format($codDue, 0) }} in cash.</span>
                                </label>
                            @endif

                            <div class="delivery-form-error" data-form-error role="alert" hidden></div>

                            <button type="submit" class="delivery-btn delivery-btn-primary delivery-btn-block">
                                Mark delivered <i class="fas fa-circle-check" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endif

                    @if (! $canAccept && ! $canArrivePickup && ! $canConfirmPickup && ! $canStartTransit && ! $canArriveDropoff && ! $canComplete)
                        <p class="delivery-action-copy">There is no action available for this delivery right now.</p>
                    @endif
                </div>
            </section>
        @endif

        @if ($supportPhone)
            <p class="delivery-support-line">
                Need help? <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}">Call Juvo support</a>
            </p>
        @endif
    </div>

    @if ($hasNavigation)
        <div class="modal fade" id="deliveryMapModal" tabindex="-1" aria-labelledby="deliveryMapTitle" aria-hidden="true">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content delivery-map-modal">
                    <div class="delivery-map-modal-head">
                        <button type="button" class="deliveries-icon-btn" data-bs-dismiss="modal" aria-label="Close map">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                        <div>
                            <span>Navigate to</span>
                            <strong id="deliveryMapTitle">{{ $navName }}</strong>
                            <p>{{ $navAddress !== '' ? $navAddress : $navQuery }}</p>
                        </div>
                    </div>

                    <div class="delivery-map-full" id="deliveryMapFull" role="img" aria-label="Full map for {{ $navName }}"></div>

                    <div class="delivery-map-sheet">
                        <div class="delivery-map-metrics">
                            <div><span>Distance</span><strong id="mapDistance">—</strong></div>
                            <div><span>ETA</span><strong id="mapEta">—</strong></div>
                        </div>
                        <a href="{{ $navUrl }}" class="delivery-btn delivery-btn-primary" target="_blank" rel="noopener" data-navigate-link data-destination="{{ $navQuery }}">
                            <i class="fas fa-diamond-turn-right" aria-hidden="true"></i> Open in Maps
                        </a>
                        <p class="delivery-map-note" id="mapRouteNote">Allow location access for a live route and ETA.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($canReport)
        <div class="modal fade" id="reportProblemModal" tabindex="-1" aria-labelledby="reportProblemTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content deliveries-modal">
                    <form id="reportProblemForm" method="POST" action="{{ route('rider.deliveries.fail', $order) }}" data-delivery-action>
                        @csrf
                        <div class="modal-body">
                            <span class="deliveries-modal-icon is-danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></span>
                            <h2 id="reportProblemTitle">Report a problem</h2>
                            <p class="deliveries-modal-copy">This will notify dispatch and end this delivery attempt.</p>

                            <div class="delivery-field">
                                <label for="failure_reason">What happened?</label>
                                <select id="failure_reason" name="reason" required>
                                    <option value="">Choose a reason</option>
                                    @foreach ($failureReasons as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="delivery-field">
                                <label for="failure_note">Details <span>Optional</span></label>
                                <textarea id="failure_note" name="note" rows="3" maxlength="1000" placeholder="Add useful details for dispatch"></textarea>
                            </div>

                            <div class="delivery-form-error" data-form-error role="alert" hidden></div>

                            <div class="deliveries-modal-actions">
                                <button type="button" class="delivery-btn delivery-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="delivery-btn delivery-btn-danger">Report problem</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @include('rider.partials.accept-delivery-modal')
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const acceptModalEl = document.getElementById('acceptDeliveryModal');
    const acceptModal = window.bootstrap && acceptModalEl ? bootstrap.Modal.getOrCreateInstance(acceptModalEl) : null;
    const acceptConfirm = acceptModalEl?.querySelector('[data-accept-confirm]');
    const acceptError = acceptModalEl?.querySelector('[data-accept-error]');
    let pendingAcceptForm = null;
    let accepting = false;

    const showFormError = (form, message) => {
        const box = form.querySelector('[data-form-error]');
        if (!box) return;
        box.textContent = message;
        box.hidden = false;
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const clearFormError = (form) => {
        const box = form.querySelector('[data-form-error]');
        if (!box) return;
        box.textContent = '';
        box.hidden = true;
    };

    const submitAjaxForm = async (form) => {
        const button = form.querySelector('button[type="submit"]');
        const original = button?.innerHTML;
        if (button) {
            button.disabled = true;
            button.classList.add('is-loading');
        }
        clearFormError(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                window.location = data.redirect || window.location.href;
                return;
            }

            const validationMessage = data.errors
                ? Object.values(data.errors).flat().filter(Boolean)[0]
                : null;

            showFormError(form, validationMessage || data.message || 'This action could not be completed. Please try again.');
        } catch (error) {
            showFormError(form, 'Network error. Check your connection and try again.');
        } finally {
            if (button) {
                button.disabled = false;
                button.classList.remove('is-loading');
                if (original) button.innerHTML = original;
            }
        }
    };

    document.querySelectorAll('form[data-delivery-action]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAjaxForm(form);
        });
    });

    document.querySelectorAll('.delivery-accept-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            pendingAcceptForm = form;
            if (acceptError) acceptError.hidden = true;

            if (!acceptModal) {
                if (window.confirm('Accept delivery #' + form.dataset.orderRef + '?')) form.submit();
                return;
            }

            acceptModalEl.querySelector('[data-accept-ref]').textContent = form.dataset.orderRef || '—';
            acceptModalEl.querySelector('[data-accept-route]').textContent = form.dataset.orderRoute || '';
            const refresh = acceptModalEl.querySelector('[data-accept-refresh]');
            if (refresh) refresh.hidden = true;
            acceptModal.show();
        });
    });

    acceptConfirm?.addEventListener('click', async () => {
        if (!pendingAcceptForm || accepting) return;

        accepting = true;
        acceptConfirm.disabled = true;
        acceptConfirm.classList.add('is-loading');
        if (acceptError) acceptError.hidden = true;

        try {
            const response = await fetch(pendingAcceptForm.action, {
                method: 'POST',
                body: new FormData(pendingAcceptForm),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                window.location = data.redirect || window.location.href;
                return;
            }

            const validationMessage = data.errors
                ? Object.values(data.errors).flat().filter(Boolean)[0]
                : null;

            let message = validationMessage || data.message || 'This delivery could not be accepted.';

            if (response.status === 409 || response.status === 422) {
                message += ' Refresh to see the latest assignment.';
            }

            if (acceptError) {
                acceptError.textContent = message;
                acceptError.hidden = false;
            }
            const refresh = acceptModalEl.querySelector('[data-accept-refresh]');
            if (refresh) refresh.hidden = false;
        } catch (error) {
            if (acceptError) {
                acceptError.textContent = 'Network error. Check your connection and try again.';
                acceptError.hidden = false;
            }
        } finally {
            accepting = false;
            acceptConfirm.disabled = false;
            acceptConfirm.classList.remove('is-loading');
        }
    });

    acceptModalEl?.querySelector('[data-accept-refresh]')?.addEventListener('click', () => {
        window.location.reload();
    });

    acceptModalEl?.addEventListener('hidden.bs.modal', () => {
        pendingAcceptForm = null;
        if (acceptError) acceptError.hidden = true;
    });

    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent);
    document.querySelectorAll('[data-navigate-link]').forEach((link) => {
        if (isIos && link.dataset.destination) {
            link.href = 'https://maps.apple.com/?daddr=' + encodeURIComponent(link.dataset.destination) + '&dirflg=d';
        }
    });

    const previewEl = document.getElementById('deliveryMapPreview');
    const fullEl = document.getElementById('deliveryMapFull');
    const previewNote = document.getElementById('mapPreviewNote');
    const routeNote = document.getElementById('mapRouteNote');
    const distanceEl = document.getElementById('mapDistance');
    const etaEl = document.getElementById('mapEta');
    const modalEl = document.getElementById('deliveryMapModal');

    if (!previewEl || !modalEl) return;

    const destination = {
        lat: Number(previewEl.dataset.mapLat),
        lng: Number(previewEl.dataset.mapLng),
        address: (previewEl.dataset.mapAddress || '').trim(),
        label: (previewEl.dataset.mapLabel || 'Destination').trim(),
    };

    let geocodePromise = null;
    let previewMap = null;
    let fullMap = null;
    let routeLayer = null;

    const hasCoords = Number.isFinite(destination.lat) && Number.isFinite(destination.lng) && destination.lat !== 0 && destination.lng !== 0;

    const markerIcon = L.divIcon({
        className: 'delivery-map-marker-wrap',
        html: '<span class="delivery-map-marker"><i class="fas fa-location-dot"></i></span>',
        iconSize: [34, 42],
        iconAnchor: [17, 40],
    });

    const resolveDestination = () => {
        if (hasCoords) return Promise.resolve(destination);
        if (geocodePromise) return geocodePromise;
        if (destination.address === '') return Promise.resolve(null);

        geocodePromise = fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=en&q=' + encodeURIComponent(destination.address), {
            headers: { 'Accept': 'application/json' },
        })
            .then((response) => response.ok ? response.json() : [])
            .then((results) => {
                if (!Array.isArray(results) || results.length === 0) return null;
                const lat = Number(results[0].lat);
                const lng = Number(results[0].lon);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
                destination.lat = lat;
                destination.lng = lng;
                return destination;
            })
            .catch(() => null);

        return geocodePromise;
    };

    const popupLabel = () => {
        const label = document.createElement('div');
        label.textContent = destination.label;
        return label;
    };

    const baseTiles = (map) => {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
    };

    const initializePreview = async () => {
        const point = await resolveDestination();

        if (!point) {
            previewEl.classList.add('is-unavailable');
            previewEl.innerHTML = '<div class="delivery-map-fallback"><i class="fas fa-map-location-dot"></i><span>Map preview unavailable. Use Navigate to open the address in Maps.</span></div>';
            if (previewNote) previewNote.textContent = 'Map preview unavailable for this address.';
            return;
        }

        previewMap = L.map(previewEl, {
            zoomControl: false,
            attributionControl: true,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
        }).setView([point.lat, point.lng], 14);

        baseTiles(previewMap);
        L.marker([point.lat, point.lng], { icon: markerIcon }).addTo(previewMap).bindPopup(popupLabel());

        if (previewNote) previewNote.textContent = 'Destination: ' + destination.label;
    };

    initializePreview();

    modalEl.addEventListener('shown.bs.modal', async () => {
        const point = await resolveDestination();

        if (!point) {
            if (routeNote) routeNote.textContent = 'This address could not be located. Use Open in Maps for navigation.';
            return;
        }

        if (!fullMap) {
            fullMap = L.map(fullEl).setView([point.lat, point.lng], 15);
            baseTiles(fullMap);
            fullMap.invalidateSize();
        }

        fullMap.eachLayer((layer) => {
            if (layer instanceof L.Marker || layer instanceof L.Polyline || layer instanceof L.Circle) fullMap.removeLayer(layer);
        });

        L.marker([point.lat, point.lng], { icon: markerIcon }).addTo(fullMap).bindPopup(popupLabel());

        if (!navigator.geolocation) {
            if (routeNote) routeNote.textContent = 'Location is not supported by this browser. Use Open in Maps for turn-by-turn directions.';
            return;
        }

        if (routeNote) routeNote.textContent = 'Finding your location...';

        navigator.geolocation.getCurrentPosition(async (position) => {
            const rider = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
                accuracy: position.coords.accuracy,
            };

            L.circleMarker([rider.lat, rider.lng], {
                radius: 7,
                color: '#ffffff',
                weight: 3,
                fillColor: '#2f6fed',
                fillOpacity: 1,
            }).addTo(fullMap).bindPopup('Your location');

            L.circle([rider.lat, rider.lng], {
                radius: rider.accuracy,
                color: '#2f6fed',
                weight: 1,
                fillOpacity: 0.08,
            }).addTo(fullMap);

            fullMap.fitBounds([
                [rider.lat, rider.lng],
                [point.lat, point.lng],
            ], { padding: [40, 40] });

            if (routeNote) routeNote.textContent = 'Calculating route...';

            try {
                const url = 'https://router.project-osrm.org/route/v1/driving/'
                    + rider.lng + ',' + rider.lat + ';'
                    + point.lng + ',' + point.lat
                    + '?overview=full&geometries=geojson';

                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                const route = data.routes?.[0];

                if (!response.ok || !route) throw new Error('No route');

                if (routeLayer && fullMap.hasLayer(routeLayer)) fullMap.removeLayer(routeLayer);
                routeLayer = L.geoJSON(route.geometry, {
                    style: { color: '#ff850f', weight: 6, opacity: 0.9, lineCap: 'round' },
                }).addTo(fullMap);

                if (distanceEl) distanceEl.textContent = (route.distance / 1000).toFixed(1) + ' km';
                if (etaEl) etaEl.textContent = Math.max(1, Math.round(route.duration / 60)) + ' min';
                if (routeNote) routeNote.textContent = 'Route and ETA by OSRM using OpenStreetMap data.';
            } catch (error) {
                if (routeNote) routeNote.textContent = 'Route and ETA are unavailable right now. Use Open in Maps for directions.';
            }
        }, () => {
            if (routeNote) routeNote.textContent = 'Location permission was not granted. Use Open in Maps for turn-by-turn directions.';
        }, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000,
        });
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        if (distanceEl) distanceEl.textContent = '—';
        if (etaEl) etaEl.textContent = '—';
        if (routeNote) routeNote.textContent = 'Allow location access for a live route and ETA.';
    });
})();
</script>
@endpush
