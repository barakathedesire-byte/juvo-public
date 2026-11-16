@extends('themes.xylo.layouts.master')
@section('title', 'Your Cart — Juvo')

@section('content')
@php
    $currency = $currency ?? activeCurrency();
    $itemCount = $groups->flatten(1)->sum('quantity');
    $subtotal = (float) ($quote['subtotal'] ?? 0);
    $discount = (float) ($quote['discount'] ?? 0);
    $shipping = (float) ($quote['shipping'] ?? 0);
    $tax = (float) ($quote['tax'] ?? 0);
    $total = (float) ($quote['total'] ?? 0);
    $freeThreshold = (float) ($quote['free_shipping_threshold'] ?? 0);
    $hasItems = $itemCount > 0;
    $remaining = $freeThreshold > 0 ? max(0, $freeThreshold - $subtotal) : 0;
    $progress = $freeThreshold > 0 ? min(100, ($subtotal / $freeThreshold) * 100) : 0;
@endphp
<div class="jv-page">
    <div class="jv-container">
        @include('themes.xylo.partials.page-region', ['key' => 'cart'])

        <nav class="jv-breadcrumb">
            <a href="{{ url('/') }}">{{ __('store.cart.breadcrumb_home') }}</a>
            <i class="fa fa-angle-right"></i>
            <span>{{ __('store.cart.breadcrumb_cart') }}</span>
        </nav>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if(! $hasItems && ! $saved->count())
            <div class="jv-cart-empty">
                <div class="jv-cart-empty-ico"><i class="fa fa-shopping-bag"></i></div>
                <h1>Your cart is empty</h1>
                <p>Browse millions of products from trusted vendors and add your favourites.</p>
                <div class="jv-cart-empty-actions">
                    <a href="{{ route('shop.index') }}" class="jv-btn jv-btn-primary jv-btn-lg"><i class="fa fa-shopping-bag"></i> Start shopping</a>
                    <a href="{{ route('categories.index') }}" class="jv-btn jv-btn-outline jv-btn-lg">Browse categories</a>
                </div>
            </div>
        @else
        @if($hasItems)
        <div class="jv-cart-head">
            <h1 class="jv-page-title">Your cart <span class="jv-cart-count-label">{{ $itemCount }} item{{ $itemCount == 1 ? '' : 's' }}</span></h1>
            <div class="jv-cart-head-actions">
                <a href="{{ route('shop.index') }}" class="jv-cart-link"><i class="fa fa-angle-left"></i> {{ __('store.cart.continue_shopping') }}</a>
                <button type="button" class="jv-cart-clear" id="jvClearCart"><i class="fa fa-trash"></i> Clear cart</button>
            </div>
        </div>
        @else
        <div class="jv-cart-head">
            <h1 class="jv-page-title">Saved for later</h1>
            <div class="jv-cart-head-actions">
                <a href="{{ route('shop.index') }}" class="jv-cart-link"><i class="fa fa-angle-left"></i> {{ __('store.cart.continue_shopping') }}</a>
            </div>
        </div>
        @endif

        <div class="jv-cart-layout">
            <div class="jv-cart-main">
                @foreach($groups as $vendorId => $groupLines)
                    @php
                        $vendorName = $groupLines->first()['vendor_name'] ?? 'Vendor';
                        $vendorQty = $groupLines->sum('quantity');
                    @endphp
                    <div class="jv-cart-group">
                        <div class="jv-cart-group-head">
                            <span class="jv-cart-vendor"><i class="fa fa-store"></i> {{ $vendorName }}</span>
                            <span class="jv-cart-vendor-meta">{{ $vendorQty }} item{{ $vendorQty == 1 ? '' : 's' }}</span>
                        </div>

                        @foreach($groupLines as $line)
                            <div class="jv-line {{ $line['available'] ? '' : 'is-unavailable' }}" data-key="{{ $line['key'] }}">
                                <a href="{{ $line['product_url'] }}" class="jv-line-media">
                                    @if($line['image'])
                                        <img src="{{ $line['image'] }}" alt="{{ $line['name'] }}" loading="lazy">
                                    @else
                                        <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="{{ $line['name'] }}" loading="lazy">
                                    @endif
                                </a>

                                <div class="jv-line-body">
                                    <a href="{{ $line['product_url'] }}" class="jv-line-name">{{ $line['name'] }}</a>

                                    @if($line['options']->count())
                                        <div class="jv-line-opts">
                                            @foreach($line['options'] as $opt)
                                                <span class="jv-opt-chip">{{ $opt['label'] }}: {{ $opt['value'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="jv-line-unit">{{ $currency->symbol }}{{ number_format($line['unit_price'], 2) }} each</div>

                                    @if($line['out_of_stock'])
                                        <span class="jv-stock-flag jv-flag-out"><i class="fa fa-exclamation-circle"></i> Out of stock</span>
                                    @elseif($line['low_stock'])
                                        <span class="jv-stock-flag jv-flag-low"><i class="fa fa-exclamation-triangle"></i> Only {{ $line['stock'] }} left</span>
                                    @else
                                        <span class="jv-stock-flag jv-flag-in"><i class="fa fa-check-circle"></i> In stock</span>
                                    @endif

                                    <div class="jv-line-actions">
                                        <button type="button" class="jv-line-act jv-save-later" data-key="{{ $line['key'] }}"><i class="fa fa-heart"></i> Save for later</button>
                                        <button type="button" class="jv-line-act jv-line-remove" data-key="{{ $line['key'] }}"><i class="fa fa-trash"></i> Remove</button>
                                    </div>
                                </div>

                                <div class="jv-line-qty">
                                    <span class="jv-line-label">Qty</span>
                                    <div class="jv-qty">
                                        <button type="button" class="jv-qty-btn jv-qty-decr" data-key="{{ $line['key'] }}" aria-label="Decrease quantity">&minus;</button>
                                        <input type="number" class="jv-qty-field" min="1" max="{{ max(1, $line['stock']) }}" value="{{ $line['quantity'] }}" data-key="{{ $line['key'] }}">
                                        <button type="button" class="jv-qty-btn jv-qty-incr" data-key="{{ $line['key'] }}" aria-label="Increase quantity">+</button>
                                    </div>
                                </div>

                                <div class="jv-line-total">
                                    <span class="jv-line-label">Subtotal</span>
                                    <strong>{{ $currency->symbol }}{{ number_format($line['line_total'], 2) }}</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @if($saved->count())
                    <div class="jv-cart-group jv-cart-saved">
                        <div class="jv-cart-group-head">
                            <span class="jv-cart-vendor"><i class="fa fa-heart"></i> Saved for later</span>
                            <span class="jv-cart-vendor-meta">{{ $saved->count() }} item{{ $saved->count() == 1 ? '' : 's' }}</span>
                        </div>

                        @foreach($saved as $line)
                            <div class="jv-line jv-line-muted" data-key="{{ $line['key'] }}">
                                <a href="{{ $line['product_url'] }}" class="jv-line-media">
                                    @if($line['image'])
                                        <img src="{{ $line['image'] }}" alt="{{ $line['name'] }}" loading="lazy">
                                    @else
                                        <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="{{ $line['name'] }}" loading="lazy">
                                    @endif
                                </a>

                                <div class="jv-line-body">
                                    <a href="{{ $line['product_url'] }}" class="jv-line-name">{{ $line['name'] }}</a>
                                    @if($line['options']->count())
                                        <div class="jv-line-opts">
                                            @foreach($line['options'] as $opt)
                                                <span class="jv-opt-chip">{{ $opt['label'] }}: {{ $opt['value'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="jv-line-actions">
                                        <button type="button" class="jv-line-act jv-line-move" data-key="{{ $line['key'] }}"><i class="fa fa-shopping-bag"></i> Move to cart</button>
                                        <button type="button" class="jv-line-act jv-line-remove" data-key="{{ $line['key'] }}"><i class="fa fa-trash"></i> Remove</button>
                                    </div>
                                </div>

                                <div class="jv-line-total">
                                    <span class="jv-line-label">Price</span>
                                    <strong>{{ $currency->symbol }}{{ number_format($line['unit_price'], 2) }}</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($hasItems)
            <aside class="jv-cart-aside">
                <div class="jv-summary jv-cart-summary">
                    <h2 class="jv-summary-head">Order summary</h2>

                    @if($freeThreshold > 0)
                        <div class="jv-freeship">
                            @if($remaining > 0)
                                <p class="jv-freeship-text">Add <strong>{{ $currency->symbol }}{{ number_format($remaining, 2) }}</strong> more for <strong>FREE delivery</strong>.</p>
                            @else
                                <p class="jv-freeship-text jv-freeship-ok"><i class="fa fa-truck"></i> You have unlocked <strong>FREE delivery</strong>.</p>
                            @endif
                            <div class="jv-freeship-bar"><span style="width: {{ number_format($progress, 1) }}%;"></span></div>
                        </div>
                    @endif

                    <div class="jv-summary-row">
                        <span>{{ __('store.cart.subtotal_label') }} ({{ $itemCount }})</span>
                        <span>{{ $currency->symbol }}{{ number_format($subtotal, 2) }}</span>
                    </div>

                    @if($discount > 0)
                        <div class="jv-summary-row jv-row-discount">
                            <span>{{ __('store.cart.discount_label') }}{{ $coupon ? ' ('.$coupon->code.')' : '' }}</span>
                            <span class="d-flex align-items-center gap-2">
                                -{{ $currency->symbol }}{{ number_format($discount, 2) }}
                                <button type="button" class="jv-coupon-x" id="jvRemoveCoupon" aria-label="Remove coupon">&times;</button>
                            </span>
                        </div>
                    @endif

                    <div class="jv-summary-row">
                        <span>Shipping</span>
                        <span>{{ $shipping > 0 ? $currency->symbol.number_format($shipping, 2) : 'Free' }}</span>
                    </div>

                    <div class="jv-summary-row">
                        <span>Tax</span>
                        <span>{{ $currency->symbol }}{{ number_format($tax, 2) }}</span>
                    </div>

                    <div class="jv-summary-row jv-summary-total">
                        <span>{{ __('store.cart.total_label') }}</span>
                        <span>{{ $currency->symbol }}{{ number_format($total, 2) }}</span>
                    </div>

                    <a href="{{ route('checkout.index') }}" class="jv-btn jv-btn-primary jv-btn-block mt-3">{{ __('store.cart.proceed_to_checkout') }}</a>

                    <ul class="jv-buy-trust">
                        <li><i class="fa fa-lock"></i> Secure checkout</li>
                        <li><i class="fa fa-undo"></i> Easy returns</li>
                        <li><i class="fa fa-headphones"></i> Buyer protection</li>
                    </ul>
                </div>

                <div class="jv-coupon-box">
                    <h3 class="jv-summary-head">{{ __('store.cart.coupon_heading') }}</h3>
                    @if($coupon)
                        <p class="jv-coupon-active"><i class="fa fa-check-circle"></i> <strong>{{ $coupon->code }}</strong> applied</p>
                    @else
                        <form id="applyCouponForm">
                            @csrf
                            <div class="mb-2">
                                <input type="text" name="code" id="coupon_code" placeholder="{{ __('store.cart.coupon_placeholder') }}" class="form-control">
                            </div>
                            <button type="submit" class="jv-btn jv-btn-outline jv-btn-block">{{ __('store.cart.apply_coupon') }}</button>
                        </form>
                    @endif
                </div>

                <div class="jv-coupon-box">
                    <h3 class="jv-summary-head"><i class="fa fa-truck"></i> Delivery estimate</h3>
                    <form method="GET" action="{{ route('cart.view') }}" id="jvCartRegionForm">
                        <label class="jv-field-label">Region</label>
                        <select name="region" id="jvCartRegion" class="form-select mb-2">
                            <option value="">Select region</option>
                            @foreach($regions as $r)
                                <option value="{{ $r->name }}" {{ $region === $r->name ? 'selected' : '' }}>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </form>
                    <label class="jv-field-label">Location (optional)</label>
                    <select id="jvCartLocation" class="form-select" data-region="{{ $region }}">
                        <option value="">Select location</option>
                    </select>
                    <div id="jvCartDeliveryFee" class="jv-cart-fee d-none"></div>
                </div>
            </aside>
            @endif
        </div>

        @if($hasItems && $recommendations->count())
            @include('themes.xylo.partials.product-rail', [
                'products' => $recommendations,
                'title' => 'You may also like',
                'icon' => 'fa fa-star',
                'currency' => $currency,
            ])
        @endif

        @if($hasItems && $recent->count())
            @include('themes.xylo.partials.product-rail', [
                'products' => $recent,
                'title' => 'Recently viewed',
                'icon' => 'fa fa-clock',
                'currency' => $currency,
            ])
        @endif

        @if($hasItems)
        <div class="jv-cart-stickybar">
            <div class="jv-csb-info">
                <span class="jv-csb-label">Total</span>
                <span class="jv-csb-total">{{ $currency->symbol }}{{ number_format($total, 2) }}</span>
            </div>
            <a href="{{ route('checkout.index') }}" class="jv-btn jv-btn-primary">Checkout <i class="fa fa-angle-right"></i></a>
        </div>
        @endif
        @endif
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    var csrf = "{{ csrf_token() }}";

    function post(url, body, onDone) {
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { if (onDone) { onDone(data); } })
        .catch(function () { toastr.error('Something went wrong. Please try again.'); });
    }

    var updateTimer = null;

    function scheduleUpdate() {
        clearTimeout(updateTimer);
        updateTimer = setTimeout(function () {
            var rows = [];
            document.querySelectorAll('.jv-qty-field').forEach(function (input) {
                rows.push({ key: input.dataset.key, quantity: parseInt(input.value, 10) || 1 });
            });
            post("{{ route('cart.update') }}", { cart: rows }, function (data) {
                if (data.success) { window.location.reload(); }
            });
        }, 550);
    }

    document.addEventListener('click', function (e) {
        var decr = e.target.closest('.jv-qty-decr');
        if (decr) {
            var field = decr.parentElement.querySelector('.jv-qty-field');
            if (field) { field.value = Math.max(1, (parseInt(field.value, 10) || 1) - 1); scheduleUpdate(); }
            return;
        }

        var incr = e.target.closest('.jv-qty-incr');
        if (incr) {
            var field2 = incr.parentElement.querySelector('.jv-qty-field');
            if (field2) {
                var max = parseInt(field2.getAttribute('max'), 10) || 1;
                field2.value = Math.min(max, (parseInt(field2.value, 10) || 1) + 1);
                scheduleUpdate();
            }
            return;
        }

        var save = e.target.closest('.jv-save-later');
        if (save) { post("{{ route('cart.saveLater') }}", { key: save.dataset.key }, reload); return; }

        var move = e.target.closest('.jv-line-move');
        if (move) { post("{{ route('cart.moveToCart') }}", { key: move.dataset.key }, reload); return; }

        var remove = e.target.closest('.jv-line-remove');
        if (remove) { post("{{ route('cart.remove') }}", { key: remove.dataset.key }, reload); }
    });

    function reload() { window.location.reload(); }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('jv-qty-field')) {
            if ((parseInt(e.target.value, 10) || 0) < 1) { e.target.value = 1; }
            scheduleUpdate();
        }
    });

    var clearBtn = document.getElementById('jvClearCart');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (window.confirm('Remove all items from your cart?')) {
                post("{{ route('cart.clear') }}", {}, reload);
            }
        });
    }

    var couponForm = document.getElementById('applyCouponForm');
    if (couponForm) {
        couponForm.addEventListener('submit', function (e) {
            e.preventDefault();
            post("{{ route('cart.applyCoupon') }}", { code: document.getElementById('coupon_code').value }, function (data) {
                toastr.success(data.message || '{{ __('store.cart.coupon_applied') }}');
                setTimeout(function () { if (data.success) { window.location.reload(); } }, 700);
            });
        });
    }

    document.getElementById('jvRemoveCoupon')?.addEventListener('click', function () {
        post("{{ route('cart.removeCoupon') }}", {}, function (data) {
            toastr.success(data.message || '{{ __('store.cart.coupon_removed') }}');
            setTimeout(function () { window.location.reload(); }, 700);
        });
    });

    var regionSelect = document.getElementById('jvCartRegion');
    if (regionSelect) {
        regionSelect.addEventListener('change', function () {
            document.getElementById('jvCartRegionForm').submit();
        });
    }

    var locationSelect = document.getElementById('jvCartLocation');
    var feeBox = document.getElementById('jvCartDeliveryFee');
    var sym = "{{ $currency->symbol }}";
    var subtotal = {{ (float) $subtotal }};

    function loadLocations(region) {
        if (!region) { return; }
        fetch("{{ url('/api/regions') }}/" + encodeURIComponent(region) + "/locations", { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                (data.locations || []).forEach(function (loc) {
                    var option = document.createElement('option');
                    option.value = loc.name;
                    option.textContent = loc.name;
                    locationSelect.appendChild(option);
                });
            });
    }

    function showFee(region, location) {
        if (!region || !location || !feeBox) { return; }
        fetch("{{ route('api.delivery.quote') }}?region=" + encodeURIComponent(region) + "&location=" + encodeURIComponent(location) + "&subtotal=" + subtotal)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var door = data.door || {};
                var pickup = data.pickup || {};
                feeBox.innerHTML =
                    '<div class="jv-fee-row"><i class="fa fa-truck"></i> Door: <strong>' + (door.available ? sym + Number(door.fee || 0).toFixed(2) : 'N/A') + '</strong>' + (door.eta ? ' <span class="jv-fee-eta">' + door.eta + '</span>' : '') + '</div>' +
                    '<div class="jv-fee-row"><i class="fa fa-store"></i> Pickup: <strong>' + (pickup.available ? sym + Number(pickup.fee || 0).toFixed(2) : 'N/A') + '</strong>' + (pickup.eta ? ' <span class="jv-fee-eta">' + pickup.eta + '</span>' : '') + '</div>';
                feeBox.classList.remove('d-none');
            });
    }

    if (locationSelect && locationSelect.dataset.region) {
        loadLocations(locationSelect.dataset.region);
        setTimeout(function () { showFee(regionSelect ? regionSelect.value : '', selectedLocationName()); }, 250);
    }
    if (locationSelect) {
        locationSelect.addEventListener('change', function () {
            var name = selectedLocationName();
            showFee(regionSelect ? regionSelect.value : '', name);
            fetch("{{ route('api.locations.remember') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ region: regionSelect ? regionSelect.value : '', location_id: locationSelect.value || null })
            }).then(function () { window.location.reload(); });
        });
    }
})();
</script>
@endsection
