<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #INV-{{ $order->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; margin: 0; background: #f3f4f6; }
        .inv-wrap { max-width: 820px; margin: 24px auto; background: #fff; padding: 32px; }
        .inv-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #f68b1e; padding-bottom: 16px; }
        .inv-head h1 { font-size: 28px; margin: 0; letter-spacing: 3px; }
        .inv-meta { text-align: right; font-size: 13px; line-height: 1.7; }
        .inv-parties { display: flex; gap: 24px; margin: 20px 0; font-size: 13px; }
        .inv-parties > div { flex: 1; }
        .inv-parties h4 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 11px; letter-spacing: .5px; color: #6b7280; }
        .num { text-align: right; white-space: nowrap; }
        .inv-totals { width: 300px; margin-left: auto; margin-top: 8px; font-size: 13px; }
        .inv-totals td { padding: 6px 10px; }
        .inv-totals .grand td { font-weight: 700; border-top: 2px solid #f68b1e; font-size: 15px; }
        .inv-note { margin-top: 24px; font-size: 12px; color: #6b7280; }
        .no-print { text-align: center; margin-top: 20px; }
        .no-print button, .no-print a { display: inline-block; padding: 9px 18px; border: 0; background: #f68b1e; color: #fff; border-radius: 6px; font-size: 14px; text-decoration: none; cursor: pointer; margin: 0 6px; }
        @media print {
            body { background: #fff; }
            .inv-wrap { margin: 0; padding: 0; max-width: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
@php $currency = activeCurrency(); @endphp
<div class="inv-wrap">
    <div class="inv-head">
        <div>
            <h1>INVOICE</h1>
            <div style="font-size:13px;margin-top:4px;">{{ getWebConfig('site_name') ?: config('app.name') }}</div>
            @if (getWebConfig('store_address'))
                <div style="font-size:12px;color:#6b7280;">{{ getWebConfig('store_address') }}</div>
            @endif
            @if (getWebConfig('phone_number'))
                <div style="font-size:12px;color:#6b7280;">{{ getWebConfig('phone_number') }}</div>
            @endif
        </div>
        <div class="inv-meta">
            <div><strong>Invoice No:</strong> INV-{{ $order->id }}</div>
            <div><strong>Order No:</strong> #{{ $order->id }}</div>
            <div><strong>Date:</strong> {{ $order->created_at?->format('d M Y') }}</div>
            <div><strong>Status:</strong> {{ $order->statusLabel() }}</div>
            <div><strong>Payment:</strong> {{ $order->payment_method }} ({{ $order->payment_status }})</div>
        </div>
    </div>

    <div class="inv-parties">
        <div>
            <h4>Billed to</h4>
            <div><strong>{{ $order->customer?->name ?: 'Customer' }}</strong></div>
            <div>{{ $order->customer?->email ?: $order->guest_email }}</div>
            @if ($order->customer?->phone) <div>{{ $order->customer->phone }}</div> @endif
        </div>
        <div>
            <h4>Ship to</h4>
            @if ($order->shippingAddress)
                <div><strong>{{ $order->shippingAddress->name }}</strong></div>
                <div>{{ $order->shippingAddress->address }}</div>
                <div>{{ $order->shippingAddress->city }} {{ $order->shippingAddress->state }}</div>
                <div>{{ $order->shippingAddress->country }}</div>
            @elseif ($order->customer)
                <div>{{ $order->customer->address ?: '—' }}</div>
            @endif
            @if ($order->tracking_number)
                <div style="margin-top:6px;"><strong>Tracking:</strong> {{ $order->carrier }} {{ $order->tracking_number }}</div>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->details as $index => $detail)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $detail->product?->translation?->name ?? __('store.category.product_name_not_available') }}</td>
                    <td class="num">{{ $detail->quantity }}</td>
                    <td class="num">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $detail->price, 2) }}</td>
                    <td class="num">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $detail->price * $detail->quantity, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="inv-totals">
        <tbody>
            <tr><td>Subtotal</td><td class="num">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->subtotal, 2) }}</td></tr>
            @if ((float) $order->discount_amount > 0)
                <tr><td>Discount {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</td><td class="num">-{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->discount_amount, 2) }}</td></tr>
            @endif
            <tr><td>Tax</td><td class="num">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->tax_amount, 2) }}</td></tr>
            <tr><td>Delivery</td><td class="num">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->delivery_fee, 2) }}</td></tr>
            <tr class="grand"><td>Total</td><td class="num">{{ $currency->symbol ?? 'TSh' }} {{ number_format((float) $order->total_amount, 2) }}</td></tr>
        </tbody>
    </table>

    <p class="inv-note">
        Thank you for shopping with us. This invoice was generated for order #{{ $order->id }}
        and reflects the amounts charged for this order.
    </p>

    <div class="no-print">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="{{ route('customer.orders.show', $order->id) }}">Back to order</a>
    </div>
</div>
</body>
</html>
