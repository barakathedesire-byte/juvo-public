@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-file-invoice-dollar me-2" style="color:#f68b1e;"></i>Payments &amp; Invoices</h4>
    </div>

    <div class="row g-3 mb-3">
        @foreach([
            ['Collected', number_format($summary['collected'], 2), '#16a34a'],
            ['Outstanding', number_format($summary['outstanding'], 2), '#dc2626'],
            ['Ad spend delivered', number_format($summary['spent'], 2), '#f68b1e'],
        ] as [$label, $value, $color])
            <div class="col-md-4">
                <div class="card shadow-sm"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <h4 class="mb-0" style="color:{{ $color }};">{{ $value }}</h4>
                </div></div>
            </div>
        @endforeach
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach(['all' => 'All', 'paid' => 'Paid', 'unpaid' => 'Unpaid', 'awaiting' => 'Awaiting payment'] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $status === $key ? 'active' : '' }}" href="{{ route('admin.ad-manager.payments.index', ['status' => $key]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Campaign</th><th>Vendor</th><th>Method</th>
                        <th class="text-end">Amount</th><th>Paid</th><th class="text-end">Spent</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($payments as $c)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td><a href="{{ route('admin.ad-manager.campaigns.show', $c->id) }}">{{ $c->title }}</a></td>
                            <td>{{ $c->vendor?->shop_name ?? '—' }}</td>
                            <td>
                                {{ $c->payment_method ?? '—' }}
                                @if (! empty($c->payment_meta['provider']))
                                    <div class="text-muted small">
                                        {{ $c->payment_meta['provider'] }} · {{ $c->payment_meta['msisdn'] ?? '—' }}
                                        @if (! empty($c->payment_meta['reference']))
                                            · ref {{ $c->payment_meta['reference'] }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($c->amount_paid, 2) }}</td>
                            <td>
                                @if($c->paid_at)<span class="badge text-bg-success">{{ $c->paid_at->toDateString() }}</span>
                                @else<span class="badge text-bg-warning">unpaid</span>@endif
                            </td>
                            <td class="text-end">{{ number_format($c->spent, 2) }}</td>
                            <td><span class="badge {{ $c->statusBadgeClass() }}">{{ $c->statusLabel() }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.ad-manager.payments.invoice', $c->id) }}" class="btn btn-sm btn-outline-secondary">Invoice</a>
                                @if(! $c->paid_at)
                                    <form method="POST" action="{{ route('admin.ad-manager.payments.verify', $c->id) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-success">Verify payment</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No payments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $payments->links() }}</div>
@endsection
