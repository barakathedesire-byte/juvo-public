@extends('vendor.layouts.master')

@section('css')
    <link rel="stylesheet" href="{{ asset('vendor/toastr/css/toastr.min.css') }}">
@endsection

@section('content')
@php
    $productName = $product->translations->firstWhere('language_code', config('app.locale'))?->name
        ?? $product->translations->first()?->name
        ?? ('#'.$product->id);
@endphp
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Inventory — {{ $productName }}</h6>
        <a href="{{ route('vendor.products.index') }}" class="btn btn-light btn-sm">Back to products</a>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <div class="text-muted small">Current stock valuation</div>
                    <div class="fs-4 fw-bold">{{ number_format($valuation, 2) }} {{ getWebConfig('default_currency', 'TZS') }}</div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Variant</th>
                        <th>SKU</th>
                        <th>Stock</th>
                        <th>Low-stock threshold</th>
                        <th style="width:340px;">Adjust stock</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($product->variants as $variant)
                        <tr>
                            <td>{{ $variant->translations->first()?->name ?? $variant->variant_slug }}</td>
                            <td>{{ $variant->SKU }}</td>
                            <td>
                                <span class="badge {{ $variant->stock <= $variant->low_stock_threshold ? 'bg-danger' : 'bg-success' }}"
                                      data-stock-badge="{{ $variant->id }}">
                                    {{ $variant->stock }}
                                </span>
                            </td>
                            <td>{{ $variant->low_stock_threshold }}</td>
                            <td>
                                <form class="d-flex gap-2" data-adjust-form="{{ $variant->id }}"
                                      action="{{ route('vendor.products.inventory.adjust', $product->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                    <input type="number" name="quantity" min="0" class="form-control form-control-sm"
                                           value="{{ $variant->stock }}" style="width:110px;">
                                    <input type="text" name="reason" class="form-control form-control-sm"
                                           placeholder="Reason (optional)">
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No variants.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h6 class="mt-4">Movement history</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Variant</th>
                        <th>Type</th>
                        <th>Change</th>
                        <th>Balance after</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $movement)
                        <tr>
                            <td>{{ $movement->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $movement->variant?->translations->first()?->name ?? '—' }}</td>
                            <td>{{ ucfirst($movement->type) }}</td>
                            <td class="{{ $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">
                                {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                            </td>
                            <td>{{ $movement->balance_after }}</td>
                            <td>{{ $movement->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No movements recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    document.querySelectorAll('[data-adjust-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: formData,
            })
                .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
                .then(function (result) {
                    if (result.ok && result.data.success) {
                        toastr.success(result.data.message);
                        const badge = document.querySelector('[data-stock-badge="' + formData.get('variant_id') + '"]');
                        if (badge) {
                            badge.textContent = result.data.stock;
                        }
                    } else {
                        toastr.error((result.data && result.data.message) || 'Could not update stock.');
                    }
                })
                .catch(function () { toastr.error('Could not update stock.'); });
        });
    });
</script>
@endsection
