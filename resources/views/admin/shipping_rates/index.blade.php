@extends('admin.layouts.admin')

@section('title', 'Shipping rates')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Shipping rates</h4>
        <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary btn-sm">Coupons</a>
    </div>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <p class="text-muted mt-2">Rates are matched by region (then country), then order subtotal band. "Free over" waives the fee above that subtotal. The global free-shipping business rule still applies.</p>

    <div class="card shadow-sm mt-2">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Region</th><th>Country</th><th>Min–Max order</th><th>Rate</th><th>Free over</th><th>ETA</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($shippingRates as $rate)
                        <tr>
                            <td colspan="9" class="p-0">
                                <form method="POST" action="{{ route('admin.shipping-rates.update', $rate->id) }}" class="row g-2 align-items-center px-3 py-2 m-0">
                                    @csrf @method('PUT')
                                    <fieldset @disabled(! \App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing')) style="display:contents">
                                    <div class="col-md-2"><input name="name" class="form-control form-control-sm" value="{{ $rate->name }}" required></div>
                                    <div class="col-md-1"><input name="region" class="form-control form-control-sm" value="{{ $rate->region }}"></div>
                                    <div class="col-md-1"><input name="country" class="form-control form-control-sm" value="{{ $rate->country }}"></div>
                                    <div class="col-md-2 d-flex gap-1">
                                        <input name="min_order_value" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rate->min_order_value }}" title="Min order">
                                        <input name="max_order_value" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rate->max_order_value }}" placeholder="max" title="Max order">
                                    </div>
                                    <div class="col-md-1"><input name="rate" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rate->rate }}" required></div>
                                    <div class="col-md-1"><input name="free_over" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rate->free_over }}" placeholder="—"></div>
                                    <div class="col-md-1"><input name="eta_label" class="form-control form-control-sm" value="{{ $rate->eta_label }}" placeholder="2-3 days"></div>
                                    <div class="col-md-1"><span class="badge {{ $rate->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $rate->is_active ? 'Active' : 'Off' }}</span><input name="sort_order" type="number" class="form-control form-control-sm mt-1" value="{{ $rate->sort_order }}" title="Sort order"></div>
                                    <div class="col-md-1 text-end">
                                        @if(\App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing'))
                                        <button class="btn btn-sm btn-primary">Save</button>
                                        <button form="toggle-{{ $rate->id }}" class="btn btn-sm btn-outline-secondary" type="submit" title="Toggle">{{ $rate->is_active ? 'Off' : 'On' }}</button>
                                        <span class="border border-danger rounded-3 d-inline-block ms-1 px-1" title="Delete" onclick="if(confirm('Delete this shipping rate?')) document.getElementById('del-{{ $rate->id }}').submit();" style="cursor:pointer;"><i class="bi bi-trash-fill text-danger"></i></span>
                                        @else
                                        <span class="text-muted small">View only</span>
                                        @endif
                                    </div>
                                    </fieldset>
                                </form>
                                <form id="del-{{ $rate->id }}" method="POST" action="{{ route('admin.shipping-rates.destroy', $rate->id) }}" class="d-none">@csrf @method('DELETE')</form>
                                <form id="toggle-{{ $rate->id }}" method="POST" action="{{ route('admin.shipping-rates.toggle', $rate->id) }}" class="d-none">@csrf</form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No shipping rates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mt-3 mb-5">
        <div class="card-header">Add a shipping rate</div>
        <div class="card-body">
            @if(\App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing'))
            <form method="POST" action="{{ route('admin.shipping-rates.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-2"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label class="form-label small">Region</label><input name="region" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><label class="form-label small">Country</label><input name="country" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Min order</label><input name="min_order_value" type="number" step="0.01" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Max order</label><input name="max_order_value" type="number" step="0.01" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Rate</label><input name="rate" type="number" step="0.01" class="form-control form-control-sm" required></div>
                    <div class="col-md-1"><label class="form-label small">Free over</label><input name="free_over" type="number" step="0.01" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">ETA label</label><input name="eta_label" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Sort</label><input name="sort_order" type="number" class="form-control form-control-sm" value="0"></div>
                    <div class="col-md-12"><button class="btn btn-sm btn-primary">Add shipping rate</button></div>
                </div>
            </form>
            @else
            <p class="text-muted mb-0">You have view-only access to shipping rates.</p>
            @endif
        </div>
    </div>
@endsection
