@extends('themes.xylo.layouts.master')
@section('content')
    @php $currency = activeCurrency(); @endphp
    <div class="jv-page">
        <div class="jv-container">
            @include('themes.xylo.partials.page-region', ['key' => 'checkout'])
            <nav class="jv-breadcrumb">
                <a href="{{ url('/') }}">{{ __('store.checkout.breadcrumb_home') }}</a>
                <i class="fa fa-angle-right"></i>
                <span>{{ __('store.checkout.breadcrumb_checkout') }}</span>
            </nav>

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @if(empty($cart))
                <p class="jv-empty">{{ __('store.cart.empty_cart') }}</p>
            @else
            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}">
                @csrf
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="jv-card mt-4">
                            <h3 class="jv-summary-head">Delivery method</h3>
                            <div class="form-check jv-check">
                                <input class="form-check-input" type="radio" name="fulfillment_type" id="fulfillment-delivery" value="delivery" @checked(old('fulfillment_type', 'delivery') === 'delivery')>
                                <label class="form-check-label" for="fulfillment-delivery">
                                    <strong>Door delivery</strong>
                                    <span class="d-block small text-muted">Delivered to your address by a rider.</span>
                                </label>
                            </div>
                            <div class="form-check jv-check mt-2">
                                <input class="form-check-input" type="radio" name="fulfillment_type" id="fulfillment-pickup" value="pickup" @checked(old('fulfillment_type') === 'pickup')>
                                <label class="form-check-label" for="fulfillment-pickup">
                                    <strong>Pickup station</strong>
                                    <span class="d-block small text-muted">Collect your order from a nearby pickup station.</span>
                                </label>
                            </div>

                            <div id="pickup-station-wrap" class="mt-3" style="display:none;">
                                <label class="form-label small" for="pickup-station">Pickup station</label>
                                <select name="pickup_location_id" id="pickup-station" class="form-select">
                                    <option value="">Select a pickup station</option>
                                </select>
                                <small class="text-muted" id="pickup-station-meta"></small>
                            </div>
                        </div>

                        <div class="jv-card mt-4">
                            <h3 class="jv-summary-head">{{ __('store.checkout.select_location') }}</h3>
                            <div class="input-group">
                                <select name="region" id="region-select" class="form-select" required>
                                    <option value="">-- {{ __('store.checkout.select_region') }} --</option>
                                    @foreach($regions as $region)
                                        <option value="{{ $region->name }}" {{ old('region', $rememberedRegion ?? '') == $region->name ? 'selected' : '' }}>{{ $region->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-primary" id="detect-location" title="Use my current location">
                                    <i class="fas fa-location-crosshairs"></i>
                                </button>
                            </div>
                            <small class="text-muted" id="geo-status"></small>
                        </div>

                        <div class="jv-card mt-4" id="shipping-card">
                            <h3 class="jv-summary-head">{{ __('store.checkout.shipping_information') }}</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="text" name="first_name" class="form-control" placeholder="{{ __('store.checkout.first_name') }}" value="{{ old('first_name', auth()->guard('customer')->user()?->name ?: '') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="last_name" class="form-control" placeholder="{{ __('store.checkout.last_name') }}" value="{{ old('last_name') }}">
                                </div>
                                <div class="col-12">
                                    <input type="text" name="address" class="form-control" placeholder="{{ __('store.checkout.address') }}" value="{{ old('address') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="suite" class="form-control" placeholder="{{ __('store.checkout.suite') }}" value="{{ old('suite') }}">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="city" id="city-input" class="form-control" placeholder="{{ __('store.checkout.city') }}" value="{{ old('city') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="zipcode" class="form-control" placeholder="{{ __('store.checkout.zipcode') }}" value="{{ old('zipcode') }}">
                                </div>
                            </div>
                        </div>

                        <div class="jv-card mt-4">
                            <h3 class="jv-summary-head">{{ __('store.checkout.contact_information') }}</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="email" name="email" class="form-control" placeholder="{{ __('store.checkout.email') }}" value="{{ old('email', auth()->guard('customer')->user()?->email ?: '') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="phone" class="form-control" placeholder="{{ __('store.checkout.phone') }}" value="{{ old('phone') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="jv-card mt-4">
                            <h3 class="jv-summary-head">Payment Method</h3>
                            <select class="form-select mb-3" id="paymentMethodSelect" name="payment_method_category" required>
                                <option value="">Select Payment Method</option>
                                <option value="instant_pay">Instant Pay</option>
                                <option value="pay_on_delivery">Pay on Delivery</option>
                            </select>
                            <div id="instantPaySection" style="display:none;">
                                <h6>Choose Payment Option</h6>
                                @forelse($paymentGateways as $gateway)
                                    <div class="form-check jv-check">
                                        <input class="form-check-input" type="radio" name="gateway" value="{{ $gateway->code }}"
                                            id="gateway-{{ $gateway->id }}" @checked(old('gateway') === $gateway->code)>
                                        <label class="form-check-label" for="gateway-{{ $gateway->id }}">{{ $gateway->name }}</label>
                                    </div>
                                @empty
                                    <div class="text-muted small">No instant payment methods are configured.</div>
                                @endforelse

                                @include('partials.mobile-money-selector', ['prefix' => 'checkout'])
                            </div>
                            <div id="podSection" style="display:none;" class="text-muted small">
                                Payment is collected at delivery. Please ensure your address is correct.
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="jv-summary">
                            <h3 class="jv-summary-head">{{ __('store.checkout.order_summary') }}</h3>
                            <div class="jv-summary-row">
                                <span>{{ __('store.checkout.subtotal') }}</span>
                                <span>{{ $currency->symbol }}{{ number_format($subtotal, 2) }}</span>
                            </div>
                            @if($discount > 0)
                                <div class="jv-summary-row text-success">
                                    <span>Discount{{ $coupon ? ' ('.$coupon->code.')' : '' }}</span>
                                    <span>-{{ $currency->symbol }}{{ number_format($discount, 2) }}</span>
                                </div>
                            @endif
                            <div class="jv-summary-row">
                                <span>{{ __('store.checkout.shipping') }}</span>
                                <span id="summary-shipping">
                                    @if($shipping > 0)
                                        {{ $currency->symbol }}{{ number_format($shipping, 2) }}
                                    @elseif($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold)
                                        <small class="text-success fw-bold">Free shipping</small>
                                    @else
                                        {{ __('store.checkout.shipping_info') }}
                                    @endif
                                </span>
                            </div>
                            @if($freeShippingThreshold > 0 && $subtotal < $freeShippingThreshold)
                                <div class="small text-muted mb-2">
                                    Add {{ $currency->symbol }}{{ number_format(max(0, $freeShippingThreshold - $subtotal), 0) }} more for free shipping.
                                </div>
                            @endif
                            <div class="jv-summary-row">
                                <span>Tax</span>
                                <span>{{ $currency->symbol }}{{ number_format($tax, 2) }}</span>
                            </div>
                            <div class="jv-summary-row jv-summary-total">
                                <span>{{ __('store.checkout.total') }}</span>
                                <span id="summary-total">{{ $currency->symbol }}{{ number_format($total, 2) }}</span>
                            </div>
                            <div class="small text-muted mb-2" id="pickup-summary-note" style="display:none;"></div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="save_address" id="save_address" value="1" {{ $customer ? 'checked' : '' }}>
                                <label class="form-check-label small" for="save_address">
                                    Save this address for future orders
                                </label>
                            </div>
                            <button type="submit" class="jv-btn jv-btn-block mt-3">{{ __('store.checkout.place_order') }}</button>
                        </div>
                    </div>
                </div>
            </form>
            @endif
        </div>
    </div>
@endsection

@section('js')
<script>
    (function () {
        const regionSelect = document.getElementById('region-select');
        const cityInput = document.getElementById('city-input');
        const geoStatus = document.getElementById('geo-status');
        const btn = document.getElementById('detect-location');

        const regionOptions = Array.from(regionSelect.options).map(o => o.value).filter(Boolean);

        function matchRegion(state) {
            if (!state) return null;
            const s = state.toLowerCase();
            return regionOptions.find(r => r.toLowerCase() === s)
                || regionOptions.find(r => r.toLowerCase().includes(s))
                || regionOptions.find(r => s.includes(r.toLowerCase()));
        }

        function useCurrentLocation() {
            if (!navigator.geolocation) {
                geoStatus.textContent = 'Geolocation is not supported by this browser.';
                return;
            }
            geoStatus.textContent = 'Requesting location permission...';
            btn.disabled = true;

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    geoStatus.textContent = 'Detecting your region...';
                    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng)
                        .then(r => r.json())
                        .then(data => {
                            const state = data.address && (data.address.state || data.address.province || data.address.county || '');
                            const city = data.address && (data.address.city || data.address.town || data.address.village || '');
                            const match = matchRegion(state);
                            if (match) {
                                regionSelect.value = match;
                            } else if (state) {
                                // Approximate: region not in list — keep city only
                                geoStatus.innerHTML = 'Could not match a delivery region &mdash; please select one manually.';
                            }
                            if (city) cityInput.value = city;
                            geoStatus.textContent = match ? 'Location detected.' : (geoStatus.textContent || '');
                        })
                        .catch(() => { geoStatus.textContent = 'Could not detect location. Please select your region manually.'; })
                        .finally(() => { btn.disabled = false; });
                },
                function () {
                    geoStatus.textContent = 'Permission denied or location unavailable. Please select your region manually.';
                    btn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
            );
        }

        btn.addEventListener('click', function () {
            if (regionSelect.value) {
                regionSelect.value = '';
            }
            cityInput.value = '';
            useCurrentLocation();
        });

        if (window.location.hash === '#detect') {
            useCurrentLocation();
        }
    })();
</script>
<script>
    (function () {
        const deliveryRadio = document.getElementById('fulfillment-delivery');
        const pickupRadio = document.getElementById('fulfillment-pickup');
        const wrap = document.getElementById('pickup-station-wrap');
        const station = document.getElementById('pickup-station');
        const meta = document.getElementById('pickup-station-meta');
        const note = document.getElementById('pickup-summary-note');
        const shippingEl = document.getElementById('summary-shipping');
        const totalEl = document.getElementById('summary-total');
        const addressInput = document.querySelector('input[name="address"]');
        const cityInput = document.getElementById('city-input');
        const regionSelect = document.getElementById('region-select');

        const locations = @json($pickupLocations ?? []);
        const currency = @json($currency->symbol ?? '');
        const rememberedLocationId = @json($rememberedLocationId ?? null);
        let rememberedApplied = false;
        const pricing = {
            subtotal: @json((float) $subtotal),
            discount: @json((float) $discount),
            tax: @json((float) $tax),
            shipping: @json((float) $shipping),
        };

        const fmt = (n) => currency + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        function isPickup() {
            return pickupRadio && pickupRadio.checked;
        }

        function fillStations() {
            if (!station) return;
            const list = locations[regionSelect.value] || [];
            station.innerHTML = '<option value="">Select a pickup station</option>';
            list.forEach(function (loc) {
                const opt = document.createElement('option');
                opt.value = loc.id;
                opt.textContent = loc.name + (loc.fee > 0 ? ' — ' + fmt(loc.fee) : ' — Free');
                opt.dataset.fee = loc.fee;
                opt.dataset.eta = loc.eta || '';
                station.appendChild(opt);
            });
            if (!rememberedApplied && rememberedLocationId) {
                const match = Array.from(station.options).find(o => o.value == rememberedLocationId);
                if (match) {
                    station.value = rememberedLocationId;
                }
                rememberedApplied = true;
            }
            updateFee();
        }

        function selectedOption() {
            return station && station.selectedIndex >= 0 ? station.options[station.selectedIndex] : null;
        }

        function updateFee() {
            if (!shippingEl || !totalEl) return;

            if (!isPickup()) {
                shippingEl.textContent = fmt(pricing.shipping);
                totalEl.textContent = fmt(pricing.subtotal - pricing.discount + pricing.shipping + pricing.tax);
                if (note) note.style.display = 'none';
                if (meta) meta.textContent = '';
                return;
            }

            const opt = selectedOption();
            const fee = opt && opt.value ? Number(opt.dataset.fee || 0) : null;

            if (fee === null) {
                shippingEl.textContent = 'Select a station';
                totalEl.textContent = fmt(pricing.subtotal - pricing.discount + pricing.tax);
                if (meta) meta.textContent = '';
                if (note) note.style.display = 'none';
                return;
            }

            shippingEl.textContent = fee > 0 ? fmt(fee) : 'Free';
            totalEl.textContent = fmt(pricing.subtotal - pricing.discount + fee + pricing.tax);
            if (meta) meta.textContent = opt.dataset.eta ? 'Ready in ' + opt.dataset.eta : '';
            if (note) {
                note.style.display = '';
                note.textContent = 'Pickup fee: ' + (fee > 0 ? fmt(fee) : 'Free');
            }
        }

        function applyMode() {
            const pickup = isPickup();
            if (wrap) wrap.style.display = pickup ? '' : 'none';
            if (station) station.required = pickup;
            [addressInput, cityInput].forEach(function (el) {
                if (!el) return;
                el.required = !pickup;
                el.disabled = pickup;
            });
            if (pickup) fillStations();
            updateFee();
        }

        if (deliveryRadio) deliveryRadio.addEventListener('change', applyMode);
        if (pickupRadio) pickupRadio.addEventListener('change', applyMode);
        if (station) station.addEventListener('change', updateFee);
        if (regionSelect) regionSelect.addEventListener('change', function () { if (isPickup()) fillStations(); });

        applyMode();
    })();
</script>
<script>
$(document).ready(function() {
    function updatePayment() {
        var val = $('#paymentMethodSelect').val();
        $('#instantPaySection').hide();
        $('#podSection').hide();
        if (val === 'instant_pay') {
            $('#instantPaySection').show();
        } else if (val === 'pay_on_delivery') {
            $('#podSection').show();
        }
        toggleMobileMoney($('#instantPaySection input[name="gateway"]:checked').val());
    }
    function toggleMobileMoney(gateway) {
        var box = $('#checkout-mobile-money');
        if (gateway === 'mobilemoney') {
            box.removeAttr('hidden');
        } else {
            box.attr('hidden', 'hidden');
        }
    }
    $('#paymentMethodSelect').on('change', updatePayment);
    $('#instantPaySection input[name="gateway"]').on('change', function() {
        toggleMobileMoney($(this).val());
    });
    updatePayment();
});
</script>

@endsection
