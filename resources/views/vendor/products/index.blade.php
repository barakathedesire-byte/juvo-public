@extends('vendor.layouts.master')

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<link href="{{ asset('vendor/bootstrap-toggle/css/bootstrap-toggle.min.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.products.title_manage') }}</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('vendor.products.create') }}" class="btn btn-light btn-sm">
                <i class="fas fa-plus me-1"></i> Add product
            </a>
            <a href="{{ route('vendor.products.export') }}" class="btn btn-outline-light btn-sm">
                <i class="fas fa-file-export me-1"></i> Export
            </a>
            <a href="{{ route('vendor.products.import') }}" class="btn btn-outline-light btn-sm">
                <i class="fas fa-file-import me-1"></i> Import
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="products-table" class="table table-bordered mt-4">
                <thead>
                    <tr>
                        <th>{{ __('cms.products.id') }}</th>
                        <th>{{ __('cms.products.name') }}</th>
                        <th>{{ __('cms.products.price') }}</th>
                        <th>{{ __('cms.products.status') }}</th>
                        <th>Approval</th>
                        <th>{{ __('cms.products.action') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('cms.products.confirm_delete') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">{{ __('cms.products.delete_confirmation') }}</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('cms.products.cancel') }}</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteProduct">{{ __('cms.products.delete') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="{{ asset('vendor/bootstrap-toggle/js/bootstrap-toggle.min.js') }}"></script>
@php
    $datatableLang = __('cms.datatables');
@endphp

@if (session('success'))
<script>
    toastr.success("{{ session('success') }}", "{{ __('cms.products.success') }}", {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 5000
    });
</script>
@endif

<script>
    $(document).ready(function () {
        const table = $('#products-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('vendor.products.data') }}",
                type: 'POST',
                data: function (d) {
                    d._token = "{{ csrf_token() }}";
                }
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'price', name: 'price' },
                {
                    data: 'status',
                    name: 'status',
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        const isChecked = data ? 'checked' : '';
                        return `<label class="switch">
                                    <input type="checkbox" class="toggle-status" data-id="${row.id}" ${isChecked}>
                                    <span class="slider round"></span>
                                </label>`;
                    }
                },
                {
                    data: 'approval',
                    name: 'approval',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ],
            pageLength: 10,
            language: @json($datatableLang),
        });

        $(document).on('change', '.toggle-status', function () {
            let productId = $(this).data('id');
            let newStatus = $(this).prop('checked') ? 1 : 0;
            updateProductStatus(productId, newStatus);
        });
    });

    let productToDeleteId = null;

    function deleteProduct(id) {
        productToDeleteId = id;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteProductModal')).show();

        $('#confirmDeleteProduct').off('click').on('click', function () {
            if (productToDeleteId !== null) {
                $.ajax({
                    url: '{{ route('vendor.products.destroy', ':id') }}'.replace(':id', productToDeleteId),
                    method: 'DELETE',
                    data: {
                        _token: "{{ csrf_token() }}",
                    },
                    success: function (response) {
                        $('#products-table').DataTable().ajax.reload();
                        toastr.error(response.message);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteProductModal')).hide();
                    },
                    error: function () {
                        toastr.error('Error deleting product.');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteProductModal')).hide();
                    }
                });
            }
        });
    }

    function updateProductStatus(id, status) {
        $.ajax({
            url: '{{ route('vendor.products.updateStatus') }}',
            method: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                id: id,
                status: status
            },
            success: function (response) {
                toastr.success(response.message);
            },
            error: function () {
                toastr.error("Failed to update status.");
            }
        });
    }
</script>
@endsection
