@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Sales Attribution</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sales.reps.index') }}" class="btn btn-sm btn-light">Salespeople</a>
            <a href="{{ route('admin.sales.leads.index') }}" class="btn btn-sm btn-light">Leads</a>
        </div>
    </div>

    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger py-2">{{ session('error') }}</div>
        @endif

        <div class="row g-3 mb-3">
            @foreach ([
                ['Attributed orders', $summary['attributed_orders']],
                ['Attributed total', number_format($summary['attributed_total'], 2)],
                ['Commission total', number_format($summary['commission_total'], 2)],
                ['Pending', $summary['pending']],
                ['Approved', $summary['approved']],
            ] as [$label, $value])
                <div class="col-md-4 col-xl">
                    <div class="border rounded-3 p-3">
                        <div class="text-muted" style="font-size:12px;">{{ $label }}</div>
                        <div class="fw-bold" style="font-size:18px;">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @can('sales_attribution.manage')
        <div class="border rounded-3 p-3 mb-3">
            <form action="{{ route('admin.sales.attribution.store') }}" method="POST" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Order ID</label>
                    <input type="number" name="order_id" min="1" class="form-control form-control-sm" required>
                    @error('order_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Salesperson</label>
                    <select name="sales_rep_id" class="form-select form-select-sm" required>
                        <option value="">Select…</option>
                        @foreach ($reps as $rep)
                            <option value="{{ $rep->id }}">{{ $rep->name }} ({{ $rep->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Notes</label>
                    <input name="notes" class="form-control form-control-sm" maxlength="500">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm w-100">Attribute</button>
                </div>
            </form>
            <div class="text-muted mt-1" style="font-size:12px;">Reassigning an order that already has an attribution requires approval.</div>
        </div>
        @endcan

        <table id="attribution-table" class="table table-bordered dt-style">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Order</th>
                    <th>Salesperson</th>
                    <th>Method</th>
                    <th>Order total</th>
                    <th>Commission</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
@php $datatableLang = __('cms.datatables'); @endphp
<script>
$(document).ready(function() {
    $('#attribution-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.sales.attribution.getData') }}",
        language: @json($datatableLang),
        columns: [
            { data: 'id', name: 'id' },
            { data: 'order_id', name: 'order_id' },
            { data: 'rep', name: 'rep', orderable: false, searchable: false },
            { data: 'method', name: 'method' },
            { data: 'order_total', name: 'order_total' },
            { data: 'commission_amount', name: 'commission_amount' },
            { data: 'status', name: 'status' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 10
    });
});
</script>
@endsection
