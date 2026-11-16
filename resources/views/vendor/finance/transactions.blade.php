@extends('vendor.layouts.master')

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="{{ asset('vendor/toastr/css/toastr.min.css') }}">
@endsection

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Transaction statement</h6>
        <a href="{{ route('vendor.finance.statement') }}" class="btn btn-light btn-sm">
            <i class="fas fa-file-csv me-1"></i> Download CSV
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tx-table" class="table table-bordered w-100">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Balance</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    $('#tx-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('vendor.finance.transactions.data') }}',
            type: 'POST',
            data: function (d) {
                d._token = '{{ csrf_token() }}';
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'type', name: 'type', orderable: false, searchable: false },
            { data: 'reference', name: 'reference' },
            { data: 'description', name: 'description', orderable: false },
            { data: 'amount', name: 'amount', orderable: false, className: 'text-end' },
            { data: 'balance_after', name: 'balance_after', orderable: false, className: 'text-end' },
        ],
        pageLength: 25,
    });
});
</script>
@endsection
