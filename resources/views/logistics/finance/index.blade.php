@extends('logistics.layouts.app')

@section('title', 'COD reconciliation')
@section('heading', 'Cash reconciliation')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">Finance</div>
            <h2 class="page-title">COD management</h2>
            <p class="page-subtitle">Cash on delivery collection and remittance status.</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="surface h-100">
                <div class="card-header">
                    <h5 class="mb-0">Outstanding COD</h5>
                    <div class="text-muted small mt-1">Delivered but not yet remitted</div>
                </div>
                <div class="card-body">
                    @if ($totals['outstanding'] > 0)
                        <div class="stat-card is-danger">
                            <div class="metric-value">{{ number_format($totals['outstanding'], 0) }}</div>
                            <div class="metric-label">Amount (TZS)</div>
                        </div>
                        <div class="mt-3">
                            <span class="badge-status status-pending">Outstanding count: {{ $totals['outstanding_count'] }}</span>
                            <small class="text-muted">Delivered orders awaiting remittance</small>
                        </div>
                    @else
                        <div class="empty-state py-3">
                            <div class="icon"><i class="fas fa-circle-check"></i></div>
                            <strong>No outstanding COD</strong>
                            <p>All delivered orders have been remitted.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="surface h-100">
                <div class="card-header">
                    <h5 class="mb-0">Awaiting collection</h5>
                    <div class="text-muted small mt-1">Delivered, COD not yet collected</div>
                </div>
                <div class="card-body">
                    @if ($totals['awaiting_count'] > 0)
                        <div class="stat-card is-warning">
                            <div class="metric-value">{{ number_format($totals['awaiting'], 0) }}</div>
                            <div class="metric-label">Amount (TZS)</div>
                        </div>
                        <div class="mt-3">
                            <span class="badge-status status-warning">Awaiting: {{ $totals['awaiting_count'] }}</span>
                            <small class="text-muted">Delivered, payment pending</small>
                        </div>
                    @else
                        <div class="empty-state py-3">
                            <div class="icon"><i class="fas fa-circle-check"></i></div>
                            <strong>No pending collections</strong>
                            <p>All delivered orders have payment collected.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-3">
        <div class="col-12">
            <div class="surface h-100">
                <div class="card-header">
                    <h5 class="mb-0">Reconciliation (last 30 days)</h5>
                    <div class="text-muted small mt-1">Remitted amounts</div>
                </div>
                <div class="card-body">
                    @if ($recentlyReconciled->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Rider</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentlyReconciled as $order)
                                        <tr>
                                            <td>{{ $order->cod_remitted_at->format('d M') }}</td>
                                            <td>{{ $order->currency ?? 'TZS' }} {{ number_format((float) $order->cod_amount, 0) }}</td>
                                            <td>{{ $order->assignedRider ? $order->assignedRider->full_name : '—' }}</td>
                                            <td>{{ $order->cod_remittance_ref ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-2">No reconciliations in 30 days</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state py-3">
                            <div class="icon"><i class="fas fa-clock"></i></div>
                            <strong>No recent reconciliations</strong>
                            <p>Remittance records will appear here after 30 days.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const outstandingCanvas = document.getElementById('revenueChart');
        if (outstandingCanvas) {
            new Chart(outstandingCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Outstanding', 'Awaiting', 'Remitted'],
                    datasets: [{
                        data: [@json($totals['outstanding'] ?? 0), @json($totals['awaiting'] ?? 0), @json($totals['reconciled_30d'] ?? 0)],
                        backgroundColor: ['#ffedef', '#fff9ec', '#eef5ff'],
                        borderColor: ['#b72f3b', '#885708', '#2859b6'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' }, tooltip: { displayColors: false } }
                }
            });
        }
    </script>
@endpush
