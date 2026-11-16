@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
        <h6 class="mb-0">{{ __('cms.refunds.details_title') }}</h6>
    </div>

    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th>{{ __('cms.refunds.id') }}</th>
                <td>{{ $refund->id }}</td>
            </tr>
            <tr>
                <th>{{ __('cms.refunds.payment') }}</th>
                <td>
                    @if($refund->payment)
                        {{ __('cms.refunds.payment') }} #{{ $refund->payment->id }} 
                        ({{ __('cms.refunds.amount') }}: {{ $refund->payment->amount }} – {{ __('cms.refunds.status') }}: {{ $refund->payment->status }})
                    @else
                        {{ __('cms.refunds.not_available') }}
                    @endif
                </td>
            </tr>
            <tr>
                <th>Order</th>
                <td>{{ $refund->order ? '#'.$refund->order->id.' ('.$refund->order->status.')' : '—' }}</td>
            </tr>
            <tr>
                <th>Customer</th>
                <td>{{ $refund->customer->name ?? '—' }}{{ $refund->customer?->email ? ' — '.$refund->customer->email : '' }}</td>
            </tr>
            <tr>
                <th>{{ __('cms.refunds.amount') }}</th>
                <td>{{ $refund->amount }} {{ $refund->currency }}</td>
            </tr>
            <tr>
                <th>{{ __('cms.refunds.status') }}</th>
                <td>{{ $refund->status }}</td>
            </tr>
            <tr>
                <th>{{ __('cms.refunds.reason') }}</th>
                <td>{{ $refund->reason ?? __('cms.refunds.not_available') }}</td>
            </tr>
            @if($refund->admin_note)
            <tr>
                <th>Admin note</th>
                <td>{{ $refund->admin_note }}</td>
            </tr>
            @endif
            <tr>
                <th>{{ __('cms.refunds.created_at') }}</th>
                <td>{{ $refund->created_at }}</td>
            </tr>
            <tr>
                <th>{{ __('cms.refunds.updated_at') }}</th>
                <td>{{ $refund->updated_at }}</td>
            </tr>
        </table>

        @if ($refund->status === 'requested')
            <div class="mt-3 d-flex gap-2">
                <form action="{{ route('admin.refunds.approve', $refund->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="admin_note" value="Return approved">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Approve this return and mark the order returned?')">
                        Approve return
                    </button>
                </form>

                <form action="{{ route('admin.refunds.reject', $refund->id) }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="admin_note" class="form-control" placeholder="Reason for rejection" required maxlength="1000">
                    <button type="submit" class="btn btn-warning text-nowrap">Reject</button>
                </form>
            </div>
        @endif

        <a href="{{ route('admin.refunds.index') }}" class="btn btn-secondary mt-3">
            {{ __('cms.refunds.back') }}
        </a>
    </div>
</div>
@endsection
