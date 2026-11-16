@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="d-flex align-items-center mb-0 dt-heading">Currencies</h6>
        @if(\App\Support\Access\AdminTabRegistry::canWrite('currencies'))
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCurrencyModal">
            <i class="fas fa-plus me-1"></i> Add Currency
        </button>
        @endif
    </div>

    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered dt-style">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Symbol</th>
                        <th>Exchange Rate</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($currencies as $currency)
                        <tr>
                            <td>{{ $currency->id }}</td>
                            <td>{{ $currency->name }}</td>
                            <td><span class="badge bg-primary">{{ $currency->code }}</span></td>
                            <td>{{ $currency->symbol }}</td>
                            <td>{{ $currency->exchange_rate }}</td>
                            <td>
                                @if(\App\Support\Access\AdminTabRegistry::canWrite('currencies'))
                                <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal"
                                        data-bs-target="#editCurrencyModal{{ $currency->id }}">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteCurrency({{ $currency->id }})">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                                @endif
                            </td>
                        </tr>

                        {{-- Edit modal --}}
                        @if(\App\Support\Access\AdminTabRegistry::canWrite('currencies'))
                        <div class="modal fade" id="editCurrencyModal{{ $currency->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form action="{{ route('admin.currencies.update', $currency->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Currency</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="name" class="form-control" value="{{ $currency->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Code</label>
                                                <input type="text" name="code" class="form-control text-uppercase" value="{{ $currency->code }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Symbol</label>
                                                <input type="text" name="symbol" class="form-control" value="{{ $currency->symbol }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Exchange Rate (1 {{ $currency->code }} = X USD)</label>
                                                <input type="number" name="exchange_rate" step="0.0001" min="0.0001" class="form-control" value="{{ $currency->exchange_rate }}" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endif
                    @empty
                        <tr><td colspan="6" class="text-center">No currencies found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add modal --}}
@if(\App\Support\Access\AdminTabRegistry::canWrite('currencies'))
<div class="modal fade" id="addCurrencyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.currencies.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Currency</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Uganda Shilling" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" class="form-control text-uppercase" placeholder="UGX" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Symbol</label>
                        <input type="text" name="symbol" class="form-control" placeholder="USh" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Exchange Rate (1 unit = X USD)</label>
                        <input type="number" name="exchange_rate" step="0.0001" min="0.0001" class="form-control" placeholder="0.00026" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Currency</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@section('js')
<script>
function deleteCurrency(id) {
    if (! confirm('Delete this currency?')) { return; }
    $.ajax({
        url: '{{ route('admin.currencies.destroy', ':id') }}'.replace(':id', id),
        method: 'DELETE',
        data: { _token: "{{ csrf_token() }}" },
        success: function (response) {
            if (response.success) { window.location.reload(); }
        },
        error: function () { alert('Error deleting currency!'); }
    });
}
</script>
@endsection