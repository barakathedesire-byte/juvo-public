<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ data_get($tracking, 'shop.name', 'Order') }} tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --juvo-orange: #f68b1e; --juvo-orange-dark: #e67e1a; --juvo-orange-light: #fff3e0; --juvo-dark: #1a1a2e; --juvo-success: #22c55e; }
        body { background: #f5f6fa; font-family: 'Inter', -apple-system, sans-serif; }
        .track-card { background: #fff; border: 1px solid #eee; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .track-header { background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%); color: #fff; border-radius: 16px 16px 0 0; }
        .timeline { list-style: none; margin: 0; padding: 0; }
        .timeline li { position: relative; padding-left: 2rem; padding-bottom: 1.25rem; border-left: 2px solid #e9ecef; margin-left: .5rem; }
        .timeline li:last-child { border-left-color: transparent; padding-bottom: 0; }
        .timeline li::before { content: ""; position: absolute; left: -7px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: #dee2e6; }
        .timeline li.completed { border-left-color: var(--juvo-success); }
        .timeline li.completed::before { background: var(--juvo-success); }
        .timeline li.current::before { background: var(--juvo-orange); box-shadow: 0 0 0 4px rgba(246,139,30,.2); }
        .timeline li.current { color: var(--juvo-dark); font-weight: 600; }
        .muted { color: #6c757d; }
        .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; }
    </style>
</head>
<body class="{{ $embed ?? false ? 'bg-white' : 'py-4' }}">
<div class="{{ $embed ?? false ? 'p-2' : 'container' }}" style="max-width: 720px;">
    @php
        $status = (string) data_get($tracking, 'status', '');
        $statusLabels = [
            'PENDING' => 'Order received',
            'ACCEPTED' => 'Order confirmed',
            'ASSIGNED' => 'Driver assigned',
            'PICKED_UP' => 'Picked up',
            'OUT_FOR_DELIVERY' => 'On the way',
            'ARRIVED' => 'Driver arrived',
            'DELIVERED' => 'Delivered',
            'FAILED' => 'Delivery failed',
            'RETURNED' => 'Returned',
            'CANCELLED' => 'Cancelled',
        ];
        $statusStyles = [
            'DELIVERED' => 'bg-success',
            'FAILED' => 'bg-danger',
            'CANCELLED' => 'bg-secondary',
            'RETURNED' => 'bg-secondary',
        ];
        $orderNumber = data_get($tracking, 'orderNumber');
        $address = data_get($tracking, 'deliveryAddress', []);
        $slot = data_get($tracking, 'timeSlot');
        $driver = data_get($tracking, 'driver');
        $pod = data_get($tracking, 'proofOfDelivery');
    @endphp

    <div class="track-card">
        <div class="track-header p-4">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <div class="small text-uppercase" style="opacity:.85;">Delivery tracking</div>
                    <h1 class="h4 mb-0">{{ data_get($tracking, 'shop.name', 'Your order') }}</h1>
                    @if ($orderNumber)
                        <div class="small mt-1" style="opacity:.85;">Order {{ $orderNumber }}</div>
                    @endif
                </div>
                <span class="badge {{ $statusStyles[$status] ?? 'bg-light text-dark' }} align-self-center px-3 py-2">
                    {{ $statusLabels[$status] ?? ucfirst(strtolower(str_replace('_', ' ', $status))) }}
                </span>
            </div>
        </div>

        <div class="p-4">
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="label mb-2">Progress</div>
                    <ul class="timeline">
                        @foreach (data_get($tracking, 'timeline', []) as $step)
                            <li class="{{ ! empty($step['completed']) ? 'completed' : '' }} {{ ! empty($step['current']) ? 'current' : '' }}">
                                {{ $step['label'] ?? ucfirst(strtolower(str_replace('_', ' ', (string) ($step['status'] ?? '')))) }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="col-12 col-md-6">
                    <div class="label mb-2">Delivery</div>
                    <p class="mb-1">
                        {{ collect([data_get($address, 'line1'), data_get($address, 'city'), data_get($address, 'province'), data_get($address, 'postalCode')])->filter()->implode(', ') ?: '—' }}
                    </p>

                    @if ($slot)
                        <p class="mb-1 muted">Window: {{ data_get($slot, 'name') ?: trim((string) data_get($slot, 'startTime') . ' – ' . (string) data_get($slot, 'endTime')) }}</p>
                    @endif

                    @if (data_get($tracking, 'estimatedArrival'))
                        <p class="mb-1 muted">Estimated arrival: {{ \Illuminate\Support\Carbon::parse(data_get($tracking, 'estimatedArrival'))->format('D, j M Y H:i') }}</p>
                    @endif

                    @if (data_get($tracking, 'actualDelivery'))
                        <p class="mb-1 muted">Delivered: {{ \Illuminate\Support\Carbon::parse(data_get($tracking, 'actualDelivery'))->format('D, j M Y H:i') }}</p>
                    @endif

                    @if ($driver)
                        <hr>
                        <div class="label mb-1">Driver</div>
                        <p class="mb-0">
                            {{ data_get($driver, 'name', 'Assigned') }}
                            @if (data_get($driver, 'vehicleType'))
                                <span class="muted">· {{ data_get($driver, 'vehicleType') }}</span>
                            @endif
                        </p>
                    @endif

                    @if (! empty(data_get($pod, 'photoUrls', [])))
                        <hr>
                        <div class="label mb-2">Proof of delivery</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ((array) data_get($pod, 'photoUrls', []) as $photo)
                                <a href="{{ $photo }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $photo }}" alt="Proof of delivery" loading="lazy" style="height: 84px; width: 84px; object-fit: cover;" class="rounded border">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="px-4 py-3 border-top small muted d-flex justify-content-between align-items-center">
            <span>Live status from {{ data_get($tracking, 'shop.name', 'the merchant') }}</span>
            <span>Updated {{ now()->format('j M Y H:i') }}</span>
        </div>
    </div>

    @unless ($embed ?? false)
        <p class="text-center small muted mt-3 mb-0">Keep this link private — it shows your delivery details.</p>
    @endunless
</div>
</body>
</html>
