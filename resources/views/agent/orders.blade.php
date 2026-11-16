@extends('agent.layouts.agent')

@section('title', 'My Orders')

@section('content')
<div class="card">
    <div class="card-body">
        <h4 class="mb-3">My Orders</h4>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Reference</th><th>Customer</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>{{ $order->order_number ?? $order->id }}</td>
                            <td>{{ optional($order->customer)->name ?? $order->guest_email }}</td>
                            <td>{{ ucfirst($order->status) }}</td>
                            <td>{{ number_format((float) $order->total_amount, 2) }}</td>
                            <td>{{ $order->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </div>
</div>
@endsection
