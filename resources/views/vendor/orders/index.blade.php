@extends('vendor.layouts.master')

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="{{ asset('vendor/toastr/css/toastr.min.css') }}">
@endsection

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
        <h6>{{ __('cms.orders.title') }}</h6>
    </div>
    <div class="card-body">
        <ul class="nav nav-pills mb-3 flex-wrap gap-1" id="orderTabs">
            @php
                $tabDefs = [
                    'all' => __('cms.orders.title') ?? 'All',
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'in_transit' => 'In transit',
                    'completed' => 'Completed',
                    'canceled' => 'Canceled / Returned',
                ];
            @endphp
            @foreach($tabDefs as $key => $label)
                <li class="nav-item">
                    <button type="button" class="nav-link {{ $key === 'all' ? 'active' : '' }}" data-status="{{ $key }}">
                        {{ $label }}
                        <span class="badge text-bg-secondary ms-1">{{ $counts[$key] ?? 0 }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
        <div class="d-flex gap-2 mb-3 align-items-center">
            <select id="bulk-action" class="form-select" style="max-width:220px;">
                <option value="">Bulk action…</option>
                <option value="accept">Accept</option>
                <option value="process">Process</option>
                <option value="dispatch">Dispatch</option>
                <option value="deliver">Deliver</option>
                <option value="complete">Complete</option>
                <option value="cancel">Cancel</option>
            </select>
            <button type="button" id="apply-bulk" class="btn btn-primary">Apply</button>
        </div>
        <div class="table-responsive">
            <table id="orders-table" class="table table-bordered mt-4 w-100">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-orders"></th>
                        <th>{{ __('cms.orders.id') }}</th>
                        <th>{{ __('cms.orders.customer') }}</th>
                        <th>{{ __('cms.orders.order_date') }}</th>
                        <th>{{ __('cms.orders.status') }}</th>
                        <th>{{ __('cms.orders.total_price') }}</th>
                        <th>{{ __('cms.orders.action') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteOrderModalLabel">{{ __('cms.orders.delete_confirm_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">{{ __('cms.orders.delete_confirm_message') }}</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('cms.orders.delete_cancel') }}</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteOrder">{{ __('cms.orders.delete_button') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
@php $datatableLang = __('cms.datatables'); @endphp

<script src="{{ asset('vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="{{ asset('vendor/toastr/js/toastr.min.js') }}"></script>

@if (session('success'))
<script>
    toastr.success("{{ session('success') }}", "{{ __('cms.orders.success') }}", {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 5000
    });
</script>
@endif

<script>
$(function () {
    const viewUrl = '{{ route('vendor.orders.show', ':id') }}';
    let activeStatus = '';
    const table = $('#orders-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('vendor.orders.data') }}",
            type: 'POST',
            data: function (d) {
                d._token = "{{ csrf_token() }}";
                d.status = activeStatus;
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    return `<input type="checkbox" class="order-check" value="${row.id}">`;
                }
            },
            { data: 'id', name: 'id' },
            { data: 'customer', name: 'customer', orderable: false, searchable: false },
            { data: 'order_date', name: 'order_date', orderable: false, searchable: false },
            { data: 'status', name: 'status' },
            { data: 'total_price', name: 'total_price', orderable: false, searchable: false },
            {
                data: 'action',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    return `<a href="${viewUrl.replace(':id', row.id)}"
                                class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-eye"></i>
                            </a>
                            <span class="border border-danger dt-trash rounded-3 d-inline-block" 
                                onclick="deleteOrder(${row.id})">
                                <i class="bi bi-trash-fill text-danger"></i>
                            </span>`;
                }
            }
        ],
        pageLength: 10,
        language: @json($datatableLang)
    });

    $('#select-all-orders').on('change', function () {
        $('#orders-table .order-check').prop('checked', $(this).prop('checked'));
    });

    $('#orderTabs').on('click', 'button[data-status]', function () {
        $('#orderTabs button').removeClass('active');
        $(this).addClass('active');
        activeStatus = $(this).data('status') === 'all' ? '' : $(this).data('status');
        table.ajax.reload();
    });

    $('#apply-bulk').on('click', function () {
        const action = $('#bulk-action').val();
        const ids = $('#orders-table .order-check:checked').map(function () {
            return parseInt($(this).val(), 10);
        }).get();

        if (!action) { toastr.warning('Choose a bulk action.'); return; }
        if (!ids.length) { toastr.warning('Select at least one order.'); return; }

        $.ajax({
            url: '{{ route('vendor.orders.bulk') }}',
            method: 'POST',
            data: { _token: "{{ csrf_token() }}", action: action, ids: ids },
            success: function (response) {
                toastr.success(response.message, "{{ __('cms.orders.success') }}");
                $('#bulk-action').val('');
                $('#select-all-orders').prop('checked', false);
                table.ajax.reload();
            },
            error: function () {
                toastr.error('Bulk action failed.', "Error");
            }
        });
    });
});

let orderToDeleteId = null;

function deleteOrder(id) {
    orderToDeleteId = id;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteOrderModal')).show();

    $('#confirmDeleteOrder').off('click').on('click', function() {
        if (orderToDeleteId !== null) {
            $.ajax({
                url: '{{ route('vendor.orders.destroy', ':id') }}'.replace(':id', orderToDeleteId),
                method: 'DELETE',
                data: { _token: "{{ csrf_token() }}" },
                success: function(response) {
                    if (response.success) {
                        $('#orders-table').DataTable().ajax.reload();
                        toastr.error(response.message, "{{ __('cms.orders.success') }}", {
                            closeButton: true,
                            progressBar: true,
                            positionClass: "toast-top-right",
                            timeOut: 5000
                        });
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteOrderModal')).hide();
                    } else {
                        toastr.error(response.message || 'Failed to delete order.', "Error", {
                            closeButton: true,
                            progressBar: true,
                            positionClass: "toast-top-right"
                        });
                    }
                },
                error: function() {
                    toastr.error('An error occurred while deleting the order.', "Error", {
                        closeButton: true,
                        progressBar: true,
                        positionClass: "toast-top-right"
                    });
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteOrderModal')).hide();
                }
            });
        }
    });
}
</script>
@endsection
