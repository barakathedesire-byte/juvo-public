@extends('admin.layouts.admin')

@section('title', 'Tax rates')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Tax rates</h4>
        <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary btn-sm">Coupons</a>
    </div>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <p class="text-muted mt-2">A rate with no country/region is the global fallback. Region-specific rates win over global; the most specific match is applied at checkout.</p>

    <div class="card shadow-sm mt-2">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Rate %</th><th>Country</th><th>Region</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($taxRates as $taxRate)
                        <tr>
                            <td colspan="6" class="p-0">
                                <form method="POST" action="{{ route('admin.tax-rates.update', $taxRate->id) }}" class="row g-2 align-items-center px-3 py-2 m-0">
                                    @csrf @method('PUT')
                                    <fieldset @disabled(! \App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing')) style="display:contents">
                                    <div class="col-md-3"><input name="name" class="form-control form-control-sm" value="{{ $taxRate->name }}" required></div>
                                    <div class="col-md-2"><input name="rate" type="number" step="0.001" min="0" max="100" class="form-control form-control-sm" value="{{ $taxRate->rate }}" required></div>
                                    <div class="col-md-3"><input name="country" class="form-control form-control-sm" value="{{ $taxRate->country }}" placeholder="e.g. Tanzania"></div>
                                    <div class="col-md-2"><input name="region" class="form-control form-control-sm" value="{{ $taxRate->region }}" placeholder="e.g. Dar es Salaam"></div>
                                    <div class="col-md-1"><span class="badge {{ $taxRate->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $taxRate->is_active ? 'Active' : 'Off' }}</span></div>
                                    <div class="col-md-1 text-end">
                                        @if(\App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing'))
                                        <button class="btn btn-sm btn-primary">Save</button>
                                        <button form="toggle-{{ $taxRate->id }}" class="btn btn-sm btn-outline-secondary" title="Toggle" type="submit">{{ $taxRate->is_active ? 'Off' : 'On' }}</button>
                                        <span class="border border-danger rounded-3 d-inline-block ms-1 px-1" title="Delete" onclick="if(confirm('Delete this tax rate?')) document.getElementById('del-{{ $taxRate->id }}').submit();" style="cursor:pointer;"><i class="bi bi-trash-fill text-danger"></i></span>
                                        @else
                                        <span class="text-muted small">View only</span>
                                        @endif
                                    </div>
                                    </fieldset>
                                </form>
                                <form id="del-{{ $taxRate->id }}" method="POST" action="{{ route('admin.tax-rates.destroy', $taxRate->id) }}" class="d-none">@csrf @method('DELETE')</form>
                                <form id="toggle-{{ $taxRate->id }}" method="POST" action="{{ route('admin.tax-rates.toggle', $taxRate->id) }}" class="d-none">@csrf</form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No tax rates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mt-3 mb-5">
        <div class="card-header">Add a tax rate</div>
        <div class="card-body">
            @if(\App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing'))
            <form method="POST" action="{{ route('admin.tax-rates.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label class="form-label small">Rate %</label><input name="rate" type="number" step="0.001" min="0" max="100" class="form-control form-control-sm" required></div>
                    <div class="col-md-3"><label class="form-label small">Country</label><input name="country" class="form-control form-control-sm" placeholder="optional"></div>
                    <div class="col-md-3"><label class="form-label small">Region</label><input name="region" class="form-control form-control-sm" placeholder="optional"></div>
                    <div class="col-md-12"><button class="btn btn-sm btn-primary">Add tax rate</button></div>
                </div>
            </form>
            @else
            <p class="text-muted mb-0">You have view-only access to tax rates.</p>
            @endif
        </div>
    </div>
@endsection
