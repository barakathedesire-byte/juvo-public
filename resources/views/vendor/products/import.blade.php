@extends('vendor.layouts.master')

@section('title', 'Import Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0"><i class="fas fa-file-import me-2" style="color:#f68b1e;"></i>Import Products</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('vendor.products.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to products
            </a>
            <a href="{{ route('vendor.products.import.sample') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download me-1"></i> Download template
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('import_errors') && count(session('import_errors')))
        <div class="alert alert-warning">
            <strong>Some rows were skipped:</strong>
            <ul class="mb-0">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white">Upload CSV</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vendor.products.import.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">CSV file</label>
                            <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                            @error('file')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Import products
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white">How it works</h6>
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li>Use the template to get the exact column headers.</li>
                        <li><strong>Required:</strong> name, price, stock.</li>
                        <li><strong>Optional:</strong> slug, description, category, discount_price, sku, barcode, status, visibility.</li>
                        <li>Rows with a matching <strong>SKU</strong> update the existing product instead of creating a duplicate.</li>
                        <li>Category can be a category name, slug, or id.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
