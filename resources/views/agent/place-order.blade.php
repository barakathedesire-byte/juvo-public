@extends('agent.layouts.agent')

@section('title', 'Place Customer Order')

@section('content')
<div class="jt-card p-3 p-md-4">
    <h2 class="jt-section-title mb-3">Place Customer Order</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="mb-3">
        <div class="progress" style="height:8px;">
            <div class="progress-bar" id="orderProgress" style="width:20%;background:var(--jt-primary);"></div>
        </div>
        <div class="d-flex justify-content-between small mt-1">
            <span>1 Customer</span><span>2 Products</span><span>3 Delivery</span><span>4 Payment</span><span>5 Review</span>
        </div>
    </div>

    <form method="POST" action="{{ route('agent.place-order.store') }}" id="placeOrderForm">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        <div class="order-step" data-step="1">
            <h5>Customer selection</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" id="customerSelect" class="form-select" required>
                        <option value="">Select customer</option>
                        @foreach ($customers as $cust)
                            <option value="{{ $cust->id }}" @selected((isset($customer) && $customer && $customer->id === $cust->id) || old('customer_id') == $cust->id)>{{ $cust->name }} ({{ $cust->phone }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Saved delivery address</label>
                    <select id="savedAddressSelect" class="form-select">
                        <option value="">Enter address manually</option>
                        @foreach ($customers as $cust)
                            @foreach ($cust->addresses as $address)
                                <option value="{{ $address->id }}" data-customer="{{ $cust->id }}" data-address="{{ $address->address }}" data-region="{{ $address->state }}" data-location="{{ $address->location_id }}" data-phone="{{ $address->phone }}">{{ $address->name ?: 'Address' }} - {{ $address->address }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <a href="{{ route('agent.customers.create') }}" class="btn btn-sm btn-outline-secondary">Add new customer</a>
                </div>
            </div>
        </div>

        <div class="order-step d-none" data-step="2">
            <h5>Product selection</h5>
            <div id="orderLines">
                <div class="row g-2 align-items-end order-line mb-2">
                    <div class="col-md-7">
                        <label class="form-label">Product variant</label>
                        <select name="variant_ids[]" class="form-select" required>
                            <option value="">Select product variant</option>
                            @foreach ($products as $product)
                                @foreach ($product->variants as $variant)
                                    <option value="{{ $variant->id }}" @selected($selectedVariantId == $variant->id)>
                                        {{ $product->translation?->name ?: 'Product #'.$product->id }} — {{ number_format((float) ($variant->discount_price ?? $variant->price), 2) }} (stock: {{ $variant->stock }})
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Qty</label>
                        <input type="number" name="quantities[]" min="1" value="{{ $selectedQuantity ?? 1 }}" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-danger removeLine">Remove</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addLine">Add product</button>
        </div>

        <div class="order-step d-none" data-step="3">
            <h5>Delivery information</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Region</label>
                    <input type="text" name="region" id="regionInput" class="form-control" value="{{ old('region', $customer->region?->name ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location ID</label>
                    <input type="number" name="location_id" id="locationInput" class="form-control" value="{{ old('location_id', $customer->location_id ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contact phone</label>
                    <input type="text" name="contact_phone" id="contactPhoneInput" class="form-control" value="{{ old('contact_phone', $customer->phone ?? '') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Delivery address</label>
                    <textarea name="delivery_address" id="deliveryAddressInput" class="form-control" rows="2">{{ old('delivery_address', $customer->delivery_address ?? $customer->address ?? '') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Delivery instructions</label>
                    <textarea name="delivery_instructions" class="form-control" rows="2">{{ old('delivery_instructions') }}</textarea>
                </div>
            </div>
        </div>

        <div class="order-step d-none" data-step="4">
            <h5>Payment selection</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Payment method</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cod" @selected(old('payment_method') === 'cod')>Cash on delivery</option>
                        <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                        @foreach ($gateways as $gateway)
                            <option value="{{ $gateway->code }}" @selected(old('payment_method') === $gateway->code)>{{ $gateway->name ?: ucfirst($gateway->code) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="order-step d-none" data-step="5">
            <h5>Review and confirm</h5>
            <p class="jt-muted">Review customer, products, delivery and payment details before submitting.</p>
            <div id="reviewSummary" class="border rounded p-3 bg-light"></div>
        </div>

        <div class="mt-3 d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" id="prevStep">Previous</button>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-dark" id="nextStep">Next</button>
                <button type="submit" class="btn btn-primary d-none" id="submitOrder" style="background:var(--jt-primary);border-color:var(--jt-primary);">Submit Order</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    let current = 1;
    const total = 5;
    const progress = document.getElementById('orderProgress');
    const steps = document.querySelectorAll('.order-step');
    const nextBtn = document.getElementById('nextStep');
    const prevBtn = document.getElementById('prevStep');
    const submitBtn = document.getElementById('submitOrder');

    function showStep(step) {
        current = step;
        steps.forEach(function (el) {
            el.classList.toggle('d-none', parseInt(el.dataset.step) !== step);
        });
        progress.style.width = (step / total * 100) + '%';
        prevBtn.disabled = step === 1;
        if (step === total) {
            nextBtn.classList.add('d-none');
            submitBtn.classList.remove('d-none');
            buildReview();
        } else {
            nextBtn.classList.remove('d-none');
            submitBtn.classList.add('d-none');
        }
    }

    nextBtn?.addEventListener('click', function () { if (current < total) showStep(current + 1); });
    prevBtn?.addEventListener('click', function () { if (current > 1) showStep(current - 1); });

    const lines = document.getElementById('orderLines');
    const addLine = document.getElementById('addLine');
    const template = lines?.querySelector('.order-line');
    addLine?.addEventListener('click', function () {
        const clone = template.cloneNode(true);
        clone.querySelectorAll('select, input').forEach(function (el) {
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            if (el.type === 'number') el.value = 1;
        });
        lines.appendChild(clone);
    });
    lines?.addEventListener('click', function (e) {
        const btn = e.target.closest('.removeLine');
        if (!btn) return;
        const rows = lines.querySelectorAll('.order-line');
        if (rows.length > 1) btn.closest('.order-line').remove();
    });

    const customerSelect = document.getElementById('customerSelect');
    const savedAddressSelect = document.getElementById('savedAddressSelect');
    function filterAddresses() {
        const customerId = customerSelect.value;
        Array.from(savedAddressSelect.options).forEach(function (opt) {
            if (!opt.value) return;
            opt.hidden = opt.dataset.customer !== customerId;
        });
        savedAddressSelect.value = '';
    }
    customerSelect?.addEventListener('change', filterAddresses);
    filterAddresses();

    savedAddressSelect?.addEventListener('change', function () {
        const opt = savedAddressSelect.selectedOptions[0];
        if (!opt || !opt.value) return;
        document.getElementById('regionInput').value = opt.dataset.region || '';
        document.getElementById('locationInput').value = opt.dataset.location || '';
        document.getElementById('contactPhoneInput').value = opt.dataset.phone || '';
        document.getElementById('deliveryAddressInput').value = opt.dataset.address || '';
    });

    function buildReview() {
        const customerText = customerSelect.selectedOptions[0]?.text || '';
        const linesText = Array.from(document.querySelectorAll('.order-line')).map(function (line) {
            const select = line.querySelector('select[name="variant_ids[]"]');
            const qty = line.querySelector('input[name="quantities[]"]').value;
            return (select.selectedOptions[0]?.text || 'Product') + ' x ' + qty;
        }).join('<br>');
        document.getElementById('reviewSummary').innerHTML =
            '<strong>Customer:</strong> ' + customerText + '<br>' +
            '<strong>Products:</strong><br>' + linesText + '<br>' +
            '<strong>Delivery:</strong> ' + (document.getElementById('deliveryAddressInput').value || '—') + '<br>' +
            '<strong>Payment:</strong> ' + document.querySelector('select[name="payment_method"]').value;
    }

    const form = document.getElementById('placeOrderForm');
    form?.addEventListener('submit', function () {
        const btn = document.getElementById('submitOrder');
        btn.disabled = true;
        btn.textContent = 'Submitting...';
    });

    showStep(1);
})();
</script>
@endsection
