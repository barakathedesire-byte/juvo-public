@extends('admin.layouts.admin')

@section('title', 'Finance Ledger')

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h4 class="mb-0">Finance Ledger</h4>
    <a href="{{ route('admin.finance.index') }}" class="btn btn-outline-secondary btn-sm">Command Center</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <select id="ledger-type" class="form-select">
                    <option value="">All types</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select id="ledger-vendor" class="form-select">
                    <option value="">All vendors</option>
                    @foreach($vendors as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <table id="ledger-table" class="table table-bordered dt-style">
            <thead>
                <tr>
                    <th>When</th><th>Vendor</th><th>Type</th><th>Amount</th>
                    <th>Balance after</th><th>Reference</th><th>Description</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    var table = $('#ledger-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.finance.ledger.data') }}",
            data: function (d) {
                d.type = $('#ledger-type').val();
                d.vendor_id = $('#ledger-vendor').val();
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'vendor_name', name: 'vendor_name', orderable: false },
            { data: 'type', name: 'type' },
            { data: 'amount', name: 'amount' },
            { data: 'balance_after', name: 'balance_after', orderable: false },
            { data: 'reference', name: 'reference' },
            { data: 'description', name: 'description', orderable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25
    });

    $('#ledger-type, #ledger-vendor').on('change', function () { table.ajax.reload(); });
});
</script>
@endsection
