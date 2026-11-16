@extends('admin.layouts.admin')

@section('css')
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="{{ asset('vendor/toastr/css/toastr.min.css') }}">
@endsection

@section('content')
    <div class="card mt-4">
        <div class="card-header card-header-bg text-white">
            <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.vendors.title_list') }}</h6>
        </div>
        <div class="card-body">
            <table id="vendors-table" class="table table-bordered mt-4 dt-style">
                <thead>
                    <tr>
                        <th>{{ __('cms.vendors.id') }}</th>
                        <th>{{ __('cms.vendors.name') }}</th>
                        <th>Store</th>
                        <th>{{ __('cms.vendors.email') }}</th>
                        <th>{{ __('cms.vendors.phone') }}</th>
                        <th>Approval</th>
                        <th>{{ __('cms.vendors.status') }}</th>
                        <th>{{ __('cms.vendors.actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <!-- Delete Modal -->
@if(\App\Support\Access\AdminTabRegistry::canWrite('vendors'))
    <div class="modal fade" id="deleteVendorModal" tabindex="-1" aria-labelledby="deleteVendorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title" id="deleteVendorModalLabel">{{ __('cms.vendors.modal_confirm_delete_title') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">{{ __('cms.vendors.modal_confirm_delete_body') }}</div>
            <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('cms.vendors.cancel') }}</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteVendor">{{ __('cms.vendors.delete') }}</button>
            </div>
        </div>
        </div>
    </div>
    @endif
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="{{ asset('vendor/toastr/js/toastr.min.js') }}"></script>

@php
    $datatableLang = __('cms.datatables'); 
@endphp

@if (session('success'))
<script>
    toastr.success("{{ session('success') }}", "{{ __('cms.vendors.success') }}", {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 5000
    });
</script>
@endif

<script>
$(document).ready(function() {
    $('#vendors-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.vendors.data') }}",
            type: "GET"
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'shop_name', name: 'shop_name', defaultContent: '—' },
            { data: 'email', name: 'email' },
            { data: 'phone', name: 'phone' },
            {
                data: 'is_approved',
                name: 'is_approved',
                render: function(data) {
                    return data
                        ? '<span class="badge bg-success">Approved</span>'
                        : '<span class="badge bg-warning text-dark">Pending</span>';
                }
            },
            { 
                data: 'status',
                name: 'status',
                render: function(data) {
                    return data === 'active' 
                        ? '<span class="badge bg-success">{{ __('cms.vendors.active') }}</span>'
                        : '<span class="badge bg-danger">{{ __('cms.vendors.inactive') }}</span>';
                }
            },
            { 
                data: 'action',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    @if(\App\Support\Access\AdminTabRegistry::canWrite('vendors'))
                    var html = '';
                    if (!row.is_approved) {
                        html += `<span class="border border-success rounded-3 d-inline-block me-1" title="Approve vendor" onclick="approveVendor(${row.id})"><i class="bi bi-check-lg text-success"></i></span>`;
                    }
                    html += `<span class="border border-danger dt-trash rounded-3 d-inline-block" onclick="deleteVendor(${row.id})"><i class="bi bi-trash-fill text-danger"></i></span>`;
                    return html;
                    @else
                    return '';
                    @endif
                }
            }
        ],
        pageLength: 10,
        language: @json($datatableLang)
    });
});

let vendorToDeleteId = null;

function approveVendor(id) {
    $.ajax({
        url: '{{ route('admin.vendors.approve', ':id') }}'.replace(':id', id),
        method: 'POST',
        data: { _token: "{{ csrf_token() }}" },
        success: function(response) {
            if (response.success) {
                $('#vendors-table').DataTable().ajax.reload();
                toastr.success(response.message, "Success", {
                    closeButton: true, progressBar: true,
                    positionClass: "toast-top-right", timeOut: 5000
                });
            }
        },
        error: function() {
            toastr.error("Could not approve vendor", "Error");
        }
    });
}

function deleteVendor(id) {
    vendorToDeleteId = id;        
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteVendorModal')).show();

    $('#confirmDeleteVendor').off('click').on('click', function() {
        if (vendorToDeleteId !== null) {
            $.ajax({
                url: '{{ route('admin.vendors.destroy', ':id') }}'.replace(':id', vendorToDeleteId),
                method: 'DELETE',
                data: {
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    if (response.success) {
                        $('#vendors-table').DataTable().ajax.reload();
                        toastr.error(response.message, "{{ __('cms.vendors.success') }}", {
                            closeButton: true,
                            progressBar: true,
                            positionClass: "toast-top-right",
                            timeOut: 5000
                        });
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteVendorModal')).hide();
                    }
                },
                error: function() {
                    toastr.error("{{ __('cms.vendors.error_delete') }}", "Error");
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteVendorModal')).hide();
                }
            });
        }
    });
}
</script>
@endsection
