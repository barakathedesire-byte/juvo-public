@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
        <h6 class="d-flex align-items-center mb-0 dt-heading">Add Payment Gateway</h6>
    </div>

    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Whoops!</strong> There were problems with your input.<br><br>
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('admin.payment-gateways.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Gateway Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="code" class="form-label">Code (unique, e.g. <code>mobilemoney</code>)</label>
                <input type="text" name="code" id="code" value="{{ old('code') }}" class="form-control text-lowercase" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea name="description" id="description" class="form-control">{{ old('description') }}</textarea>
            </div>

            <div class="form-check mb-4">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" checked>
                <label class="form-check-label" for="is_active">Active</label>
            </div>

            <h5 class="mb-3">Configurations</h5>
            <div id="configRows"></div>
            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addConfigRow">
                <i class="fas fa-plus me-1"></i> Add Configuration
            </button>

            <div class="mt-2">
                <button type="submit" class="btn btn-primary">Save Gateway</button>
                <a href="{{ route('admin.payment-gateways.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script>
let configIndex = 0;
function addConfigRow(keyName, keyValue, environment, isEncrypted) {
    const row = document.createElement('div');
    row.className = 'border rounded p-3 mb-3';
    row.innerHTML = `
        <div class="mb-3">
            <label class="form-label">Key Name</label>
            <input type="text" name="configs[${configIndex}][key_name]" class="form-control" value="${keyName || ''}" placeholder="e.g. client_id">
        </div>
        <div class="mb-3">
            <label class="form-label">Key Value</label>
            <input type="text" name="configs[${configIndex}][key_value]" class="form-control" value="${keyValue || ''}">
        </div>
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Environment</label>
                <select name="configs[${configIndex}][environment]" class="form-select">
                    <option value="sandbox" ${environment === 'production' ? '' : 'selected'}>Sandbox</option>
                    <option value="production" ${environment === 'production' ? 'selected' : ''}>Production</option>
                </select>
            </div>
            <div class="col-md-4 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input type="hidden" name="configs[${configIndex}][is_encrypted]" value="0">
                    <input type="checkbox" class="form-check-input" name="configs[${configIndex}][is_encrypted]" value="1" ${isEncrypted ? 'checked' : ''}>
                    <label class="form-check-label">Encrypted</label>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">Remove</button>
    `;
    document.getElementById('configRows').appendChild(row);
    configIndex++;
}
document.getElementById('addConfigRow').addEventListener('click', function () { addConfigRow(); });
addConfigRow('', '', 'sandbox', true);
</script>
@endsection