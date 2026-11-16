@extends('admin.layouts.admin')
@section('title', 'New Storefront Template')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">New Storefront Template</h4>
            <small class="text-muted">A global template is the default for all vendors; a vendor-specific template overrides it.</small>
        </div>
        <a href="{{ route('admin.storefront.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.storefront.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required maxlength="255" value="{{ old('name') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Scope</label>
                    <select name="scope" class="form-select" id="scopeSelect" required>
                        <option value="global" {{ old('scope') === 'global' ? 'selected' : '' }}>Global (default for all vendors)</option>
                        <option value="vendor" {{ old('scope') === 'vendor' ? 'selected' : '' }}>Vendor-specific</option>
                    </select>
                </div>
                <div class="mb-3 {{ old('scope') === 'vendor' ? '' : 'd-none' }}" id="vendorField">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-select">
                        <option value="">Select vendor</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" {{ (int) old('vendor_id') === $vendor->id ? 'selected' : '' }}>{{ $vendor->shop_name ?: $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create &amp; Edit</button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    var scopeSelect = document.getElementById('scopeSelect');
    if (scopeSelect) {
        scopeSelect.addEventListener('change', function () {
            document.getElementById('vendorField').classList.toggle('d-none', this.value !== 'vendor');
        });
    }
</script>
@endsection
