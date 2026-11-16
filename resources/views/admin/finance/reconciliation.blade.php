@extends('admin.layouts.admin')

@section('title', 'Vendor Reconciliation')

@section('content')
@php($money = fn ($v) => number_format((float) $v, 2))

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h4 class="mb-0">Vendor Reconciliation</h4>
    <a href="{{ route('admin.finance.index') }}" class="btn btn-outline-secondary btn-sm">Command Center</a>
</div>

<form method="GET" class="mb-3">
    <div class="input-group" style="max-width: 380px">
        <input name="q" value="{{ $search }}" class="form-control" placeholder="Search vendor by name or email">
        <button class="btn btn-primary">Search</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th class="text-end">Gross</th>
                    <th class="text-end">Commission</th>
                    <th class="text-end">Pending</th>
                    <th class="text-end">Available</th>
                    <th class="text-end">Reserved</th>
                    <th class="text-end">Withdrawable</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Adjustments</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>
                            <a href="{{ route('admin.vendors.show', $row['vendor']->id) }}">{{ $row['vendor']->name }}</a>
                        </td>
                        <td class="text-end">{{ $money($row['gross']) }}</td>
                        <td class="text-end">{{ $money($row['commission']) }}</td>
                        <td class="text-end">{{ $money($row['pending']) }}</td>
                        <td class="text-end">{{ $money($row['available']) }}</td>
                        <td class="text-end">{{ $money($row['reserved']) }}</td>
                        <td class="text-end fw-semibold">{{ $money($row['withdrawable']) }}</td>
                        <td class="text-end">{{ $money($row['paid']) }}</td>
                        <td class="text-end">{{ $money($row['adjustments']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">No vendors found.</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Totals</td>
                        <td></td>
                        <td></td>
                        <td class="text-end">{{ $money($totals['pending']) }}</td>
                        <td class="text-end">{{ $money($totals['available']) }}</td>
                        <td class="text-end">{{ $money($totals['reserved']) }}</td>
                        <td class="text-end">{{ $money($totals['withdrawable']) }}</td>
                        <td class="text-end">{{ $money($totals['paid']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
