<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ucfirst(str_replace('_', ' ', $type)) }} · Order #{{ $order->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; margin: 24px; font-size: 13px; }
        .doc-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f68b1e; padding-bottom: 12px; }
        .brand { font-size: 20px; font-weight: 700; color: #f68b1e; }
        .muted { color: #777; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
        th { background: #fff3e6; }
        .text-end { text-align: right; }
        .totals td { border: none; }
        .totals .label { text-align: right; font-weight: 600; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #f68b1e; color: #fff; font-size: 11px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="doc-head">
        <div>
            <div class="brand">Juvo Market</div>
            <div class="muted">{{ optional($order->vendor)->name ?? 'Juvo Vendor' }}</div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:16px;font-weight:700;">{{ ucfirst(str_replace('_', ' ', $type)) }}</div>
            <div class="muted">Order #{{ $order->id }}</div>
            <div class="muted">{{ $order->created_at?->format('Y-m-d H:i') }}</div>
            <div class="badge">{{ $order->statusLabel() }}</div>
        </div>
    </div>

    <table style="margin-top:20px;">
        <tr>
            <td style="border:none;width:50%;vertical-align:top;">
                <div class="muted">Billed to</div>
                <div>{{ optional($order->customer)->name ?? $order->guest_email ?? 'Guest' }}</div>
                <div class="muted">{{ optional($order->customer)->email ?? '' }}</div>
            </td>
            <td style="border:none;vertical-align:top;">
                <div class="muted">Ship to</div>
                @if ($order->shippingAddress)
                    <div>{{ $order->shippingAddress->name }}</div>
                    <div>{{ $order->shippingAddress->address }}</div>
                    <div>{{ $order->shippingAddress->city }} {{ $order->shippingAddress->postal_code }} {{ $order->shippingAddress->country }}</div>
                @else
                    <div class="muted">N/A</div>
                @endif
                @if ($order->tracking_number) <div class="muted">Tracking: {{ $order->tracking_number }}</div> @endif
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Variant</th>
                @unless ($type === 'packing_slip')<th class="text-end">Price</th>@endunless
                <th class="text-end">Qty</th>
                @unless ($type === 'packing_slip')<th class="text-end">Subtotal</th>@endunless
            </tr>
        </thead>
        <tbody>
            @foreach ($order->details as $detail)
                <tr>
                    <td>{{ optional(optional($detail->product)->translation)->name ?? ('#'.$detail->product_id) }}</td>
                    <td>{{ optional($detail->variant)->sku ?? '—' }}</td>
                    @unless ($type === 'packing_slip')<td class="text-end">{{ number_format((float) $detail->price, 2) }}</td>@endunless
                    <td class="text-end">{{ $detail->quantity }}</td>
                    @unless ($type === 'packing_slip')<td class="text-end">{{ number_format((float) $detail->price * $detail->quantity, 2) }}</td>@endunless
                </tr>
            @endforeach
        </tbody>
    </table>

    @unless ($type === 'packing_slip')
        <table class="totals" style="width:50%;margin-left:auto;">
            <tr><td class="label">Subtotal</td><td class="text-end">{{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</td></tr>
            <tr><td class="label">Delivery</td><td class="text-end">{{ $order->currency }} {{ number_format((float) $order->delivery_fee, 2) }}</td></tr>
            <tr><td class="label">Total</td><td class="text-end"><strong>{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</strong></td></tr>
            <tr><td class="label">Payment</td><td class="text-end">{{ $order->payment_method }} ({{ $order->payment_status }})</td></tr>
        </table>
    @endunless

    <p class="muted" style="margin-top:24px;">Thank you for shopping with Juvo Market.</p>
</body>
</html>
