@extends('admin.layouts.admin')

@section('title', 'Coupons')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Coupons &amp; discounts</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tax-rates.index') }}" class="btn btn-outline-secondary btn-sm">Tax rates</a>
            <a href="{{ route('admin.shipping-rates.index') }}" class="btn btn-outline-secondary btn-sm">Shipping rates</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <p class="text-muted mt-2">Codes are applied in the cart and honoured at checkout (discount, min-order cap, usage limit).</p>

    <div class="card shadow-sm mt-2">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Value</th>
                        <th>Min order</th>
                        <th>Max discount</th>
                        <th>Uses</th>
                        <th>Window</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coupons as $coupon)
                        <tr>
                            <td colspan="8" class="p-0">
                                <form method="POST" action="{{ route('admin.coupons.update', $coupon->id) }}" class="row g-2 align-items-center px-3 py-2 m-0">
                                    @csrf @method('PUT')
                                    <fieldset @disabled(! \App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing')) style="display:contents">
                                    <div class="col-md-2"><input name="code" class="form-control form-control-sm text-uppercase" value="{{ $coupon->code }}" required></div>
                                    <div class="col-md-2 d-flex gap-1">
                                        <input name="discount" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $coupon->discount }}" required>
                                        <select name="type" class="form-select form-select-sm" style="width:96px">
                                            <option value="percentage" {{ $coupon->type === 'percentage' ? 'selected' : '' }}>%</option>
                                            <option value="fixed" {{ $coupon->type === 'fixed' ? 'selected' : '' }}>Fixed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1"><input name="min_order_value" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $coupon->min_order_value }}" placeholder="none"></div>
                                    <div class="col-md-1"><input name="max_discount" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $coupon->max_discount }}" placeholder="none"></div>
                                    <div class="col-md-1 d-flex align-items-center gap-1">
                                        <input name="usage_limit" type="number" min="0" class="form-control form-control-sm" value="{{ $coupon->usage_limit }}" placeholder="∞" title="Usage limit">
                                        <small class="text-muted text-nowrap">{{ $coupon->used_count }} used</small>
                                    </div>
                                    <div class="col-md-2 d-flex gap-1">
                                        <input name="starts_at" type="date" class="form-control form-control-sm" value="{{ optional($coupon->starts_at)->format('Y-m-d') }}">
                                        <input name="expires_at" type="date" class="form-control form-control-sm" value="{{ optional($coupon->expires_at)->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-1">
                                        <span class="badge {{ $coupon->isActive() ? 'bg-success' : 'bg-secondary' }}">{{ $coupon->isActive() ? 'Active' : 'Inactive' }}</span>
                                        <input name="per_customer_limit" type="number" min="0" class="form-control form-control-sm mt-1" value="{{ $coupon->per_customer_limit }}" placeholder="Per customer" title="Per-customer limit">
                                    </div>
                                    <div class="col-md-2 text-end">
                                        @if(\App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing'))
                                        <button class="btn btn-sm btn-primary">Save</button>
                                        <button form="toggle-{{ $coupon->id }}" class="btn btn-sm btn-outline-secondary" title="Toggle status" type="submit">{{ $coupon->is_active ? 'Disable' : 'Enable' }}</button>
                                        <span class="border border-danger rounded-3 d-inline-block ms-1 px-1" title="Delete" onclick="if(confirm('Delete this coupon?')) document.getElementById('del-{{ $coupon->id }}').submit();" style="cursor:pointer;"><i class="bi bi-trash-fill text-danger"></i></span>
                                        @else
                                        <span class="text-muted small">View only</span>
                                        @endif
                                    </div>
                                    </fieldset>
                                </form>
                                <form id="del-{{ $coupon->id }}" method="POST" action="{{ route('admin.coupons.destroy', $coupon->id) }}" class="d-none">@csrf @method('DELETE')</form>
                                <form id="toggle-{{ $coupon->id }}" method="POST" action="{{ route('admin.coupons.toggle', $coupon->id) }}" class="d-none">@csrf</form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No coupons yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mt-3 mb-5">
        <div class="card-header">Add a coupon</div>
        <div class="card-body">
            @if(\App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing'))
            <form method="POST" action="{{ route('admin.coupons.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-2"><label class="form-label small">Code</label><input name="code" class="form-control form-control-sm text-uppercase" required></div>
                    <div class="col-md-1"><label class="form-label small">Discount</label><input name="discount" type="number" step="0.01" min="0" class="form-control form-control-sm" required></div>
                    <div class="col-md-1"><label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm"><option value="percentage">%</option><option value="fixed">Fixed</option></select>
                    </div>
                    <div class="col-md-2"><label class="form-label small">Description</label><input name="description" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Min order</label><input name="min_order_value" type="number" step="0.01" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Max discount</label><input name="max_discount" type="number" step="0.01" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Usage limit</label><input name="usage_limit" type="number" min="0" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Per customer</label><input name="per_customer_limit" type="number" min="0" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Starts</label><input name="starts_at" type="date" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><label class="form-label small">Expires</label><input name="expires_at" type="date" class="form-control form-control-sm"></div>
                    <div class="col-md-12"><button class="btn btn-sm btn-primary">Add coupon</button></div>
                </div>
            </form>
            @else
            <p class="text-muted mb-0">You have view-only access to coupons.</p>
            @endif
        </div>
    </div>
@endsection
