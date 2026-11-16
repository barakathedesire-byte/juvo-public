@php
    $activeSearch = trim((string) ($search ?? request('q', '')));
    $statusMeta = [
        'assigned' => ['label' => 'New request', 'class' => 'is-new', 'icon' => 'fa-bolt'],
        'accepted' => ['label' => 'Assigned', 'class' => 'is-assigned', 'icon' => 'fa-clipboard-check'],
        'picked_up' => ['label' => 'Picked up', 'class' => 'is-picked', 'icon' => 'fa-box'],
        'in_transit' => ['label' => 'In transit', 'class' => 'is-transit', 'icon' => 'fa-motorcycle'],
        'delivered' => ['label' => 'Completed', 'class' => 'is-done', 'icon' => 'fa-circle-check'],
        'failed' => ['label' => 'Failed', 'class' => 'is-issue', 'icon' => 'fa-triangle-exclamation'],
        'returned' => ['label' => 'Returned', 'class' => 'is-issue', 'icon' => 'fa-rotate-left'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'is-issue', 'icon' => 'fa-ban'],
    ];
@endphp

@forelse ($orders as $order)
    @php
        $status = $order->logistics_status ?? 'pending';
        $meta = $statusMeta[$status] ?? ['label' => $order->logisticsStatusLabel(), 'class' => 'is-pending', 'icon' => 'fa-clock'];
        $currency = $order->currency ?: getWebConfig('default_currency', 'TZS');
        $pickupName = $order->vendor?->shop_name ?: ($order->vendor?->name ?: ($order->pickupLocation?->name ?? 'Pickup point'));
        $pickupAddress = trim((string) ($order->vendor?->address ?? ''));
        $dropoffName = $order->shippingAddress?->name
            ?: ($order->customer?->name ?: 'Customer');
        $dropoffAddress = trim(implode(', ', array_filter([
            $order->shippingAddress?->address,
            $order->shippingAddress?->city,
            $order->shippingAddress?->state,
        ])));
        $itemCount = (int) $order->details->sum('quantity');
    @endphp

    <article class="delivery-card {{ $status === 'assigned' ? 'is-new-request' : '' }}" data-delivery-card>
        <div class="delivery-card-top">
            <div>
                <span class="delivery-card-kicker">Order</span>
                <a href="{{ route('rider.deliveries.show', $order) }}" class="delivery-card-ref">#{{ $order->id }}</a>
            </div>
            <span class="delivery-status {{ $meta['class'] }}">
                <i class="fas {{ $meta['icon'] }}" aria-hidden="true"></i>
                {{ $meta['label'] }}
            </span>
        </div>

        <div class="delivery-card-route">
            <div class="delivery-route-stop">
                <span class="delivery-route-dot is-pickup"><i class="fas fa-store" aria-hidden="true"></i></span>
                <div class="delivery-route-copy">
                    <span>Pickup</span>
                    <strong>{{ $pickupName }}</strong>
                    @if ($pickupAddress !== '')
                        <small>{{ $pickupAddress }}</small>
                    @endif
                </div>
            </div>

            <span class="delivery-route-line" aria-hidden="true"></span>

            <div class="delivery-route-stop">
                <span class="delivery-route-dot is-dropoff"><i class="fas fa-location-dot" aria-hidden="true"></i></span>
                <div class="delivery-route-copy">
                    <span>Drop-off</span>
                    <strong>{{ $dropoffName }}</strong>
                    <small>{{ $dropoffAddress !== '' ? $dropoffAddress : 'Address not provided' }}</small>
                </div>
            </div>
        </div>

        <div class="delivery-card-meta">
            <span><i class="far fa-clock" aria-hidden="true"></i> {{ $order->created_at?->diffForHumans() }}</span>
            @if ($itemCount > 0)
                <span><i class="fas fa-bag-shopping" aria-hidden="true"></i> {{ $itemCount }} {{ Str::plural('item', $itemCount) }}</span>
            @endif
            @if ($status === 'delivered' && $order->delivered_at)
                <span><i class="fas fa-circle-check" aria-hidden="true"></i> {{ $order->delivered_at->format('d M, H:i') }}</span>
            @endif
        </div>

        <div class="delivery-card-footer">
            <div class="delivery-card-fee">
                <span>Delivery fee</span>
                <strong>{{ $currency }} {{ number_format((float) $order->delivery_fee, 0) }}</strong>
            </div>

            <div class="delivery-card-actions">
                <a href="{{ route('rider.deliveries.show', $order) }}" class="delivery-btn delivery-btn-ghost">
                    {{ $status === 'delivered' ? 'Summary' : ($status === 'assigned' ? 'Details' : 'Open') }}
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>

                @if ($status === 'assigned')
                    <form method="POST"
                          action="{{ route('rider.deliveries.accept', $order) }}"
                          class="delivery-accept-form"
                          data-order-ref="{{ $order->id }}"
                          data-order-route="{{ $pickupName }} to {{ $dropoffName }}">
                        @csrf
                        <button type="submit" class="delivery-btn delivery-btn-primary" data-accept-button>
                            Accept
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </article>
@empty
    <div class="deliveries-empty">
        <span class="deliveries-empty-icon"><i class="fas fa-box-open" aria-hidden="true"></i></span>
        <h3>No deliveries here</h3>
        <p>
            @if ($activeSearch !== '')
                No assigned deliveries match your search.
            @else
                New assigned deliveries will appear here.
            @endif
        </p>
        @if ($activeSearch !== '')
            <button type="button" class="delivery-btn delivery-btn-ghost" data-clear-search>Clear search</button>
        @endif
    </div>
@endforelse

@if ($orders->hasPages())
    <div class="deliveries-pagination" data-deliveries-pagination>
        {{ $orders->onEachSide(1)->links() }}
    </div>
@endif
