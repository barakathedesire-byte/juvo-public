@extends('admin.layouts.admin')

@section('content')
    @php
        $invoiceNo = 'AD-'.$campaign->created_at->format('Y').'-'.str_pad($campaign->id, 5, '0', STR_PAD_LEFT);
    @endphp

    <style>@media print { .d-print-none, .jv-sidebar, nav, #content > nav { display:none !important; } #content { width:100% !important; } }</style>

    <div class="d-flex justify-content-between align-items-center mt-4 mb-3 d-print-none">
        <h4 class="mb-0">Invoice {{ $invoiceNo }}</h4>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print me-1"></i>Print / Save PDF</button>
            <a href="{{ route('admin.ad-manager.payments.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="card shadow-sm mx-auto" style="max-width:820px;">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between mb-4">
                <div>
                    <h3 style="color:#f68b1e;" class="mb-1">Juvo Marketplace</h3>
                    <div class="text-muted small">Advertising Invoice</div>
                </div>
                <div class="text-end small">
                    <div><strong>Invoice:</strong> {{ $invoiceNo }}</div>
                    <div><strong>Date:</strong> {{ $campaign->created_at->toDateString() }}</div>
                    <div><strong>Status:</strong> {{ $campaign->paid_at ? 'Paid' : 'Unpaid' }}</div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <div class="text-muted small text-uppercase">Billed to</div>
                    <div class="fw-semibold">{{ $campaign->vendor?->shop_name ?? $campaign->vendor?->name ?? '—' }}</div>
                    <div class="small">{{ $campaign->vendor?->email }}</div>
                </div>
                <div class="col-6 text-end">
                    <div class="text-muted small text-uppercase">Campaign</div>
                    <div class="fw-semibold">{{ $campaign->title }}</div>
                    <div class="small">{{ $campaign->placement?->name ?? 'Ad placement' }}</div>
                </div>
            </div>

            <table class="table">
                <thead class="table-light">
                    <tr><th>Description</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            {{ $campaign->package?->name ?? 'Advertising campaign' }}
                            @if($campaign->start_date && $campaign->end_date)
                                <div class="small text-muted">
                                    {{ $campaign->start_date->toDateString() }} → {{ $campaign->end_date->toDateString() }}
                                </div>
                            @endif
                            <div class="small text-muted">Billing model: {{ strtoupper($campaign->billing_model) }}</div>
                        </td>
                        <td class="text-end">{{ number_format($campaign->total_budget, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-end fw-semibold">Total</td>
                        <td class="text-end fw-semibold">{{ number_format($campaign->amount_paid, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-end text-muted">Amount paid</td>
                        <td class="text-end">{{ number_format($campaign->amount_paid, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-end text-muted">Ad spend delivered</td>
                        <td class="text-end">{{ number_format($campaign->spent, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="small text-muted mt-4">
                Payment method: {{ $campaign->payment_method ?? '—' }}
                @if($campaign->paid_at) · Paid on {{ $campaign->paid_at->toDateTimeString() }}@endif
            </div>
            <div class="small text-muted mt-2">Thank you for advertising with Juvo.</div>
        </div>
    </div>
@endsection
