@extends('admin.layouts.admin')

@php
    $basisLabels = [
        'order_total' => 'Order total',
        'subtotal' => 'Subtotal',
        'merch_after_discount' => 'Merchandise after discount',
        'merch_after_discount_ex_tax' => 'Merchandise after discount (ex tax)',
    ];
@endphp

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Agent Commission Policies</h6>
        <a href="{{ route('admin.sales-agents.index') }}" class="btn btn-sm btn-light">Back</a>
    </div>
    <div class="card-body">
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <h6>Publish a new policy</h6>
        <form action="{{ route('admin.sales-agents.policies.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <input name="name" class="form-control" value="{{ old('name') }}" required maxlength="150">
            </div>
            <div class="col-md-2">
                <label class="form-label">Policy key</label>
                <input name="policy_key" class="form-control" value="{{ old('policy_key') }}" maxlength="100" placeholder="e.g. default">
            </div>
            <div class="col-md-2">
                <label class="form-label">Rate %</label>
                <input type="number" step="0.0001" min="0" max="100" name="rate" class="form-control" value="{{ old('rate', 5) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Flat amount</label>
                <input type="number" step="0.01" min="0" name="fixed_amount" class="form-control" value="{{ old('fixed_amount', 0) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Basis</label>
                <select name="basis" class="form-select">
                    @foreach ($bases as $basis)
                        <option value="{{ $basis }}" @selected(old('basis') === $basis)>{{ $basisLabels[$basis] ?? $basis }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Min order value</label>
                <input type="number" step="0.01" min="0" name="min_order_value" class="form-control" value="{{ old('min_order_value') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Max commission</label>
                <input type="number" step="0.01" min="0" name="max_commission" class="form-control" value="{{ old('max_commission') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Priority</label>
                <input type="number" min="0" name="priority" class="form-control" value="{{ old('priority', 0) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Effective from</label>
                <input type="datetime-local" name="effective_from" class="form-control" value="{{ old('effective_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Effective to</label>
                <input type="datetime-local" name="effective_to" class="form-control" value="{{ old('effective_to') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label d-block">Include</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="include_tax" value="1" id="inc_tax" @checked(old('include_tax'))>
                    <label class="form-check-label" for="inc_tax">Tax</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="include_delivery_fee" value="1" id="inc_del" @checked(old('include_delivery_fee'))>
                    <label class="form-check-label" for="inc_del">Delivery</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label d-block">Allow</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="allow_self_purchase" value="1" id="self_p" @checked(old('allow_self_purchase'))>
                    <label class="form-check-label" for="self_p">Self purchase</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="allow_guest_orders" value="1" id="guest_o" @checked(old('allow_guest_orders', true))>
                    <label class="form-check-label" for="guest_o">Guest orders</label>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Team rate %</label>
                <input type="number" step="0.0001" min="0" max="100" name="team_rate" class="form-control" value="{{ old('team_rate', 0) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Team max depth</label>
                <input type="number" min="0" max="5" name="team_max_depth" class="form-control" value="{{ old('team_max_depth', 1) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Team cap %</label>
                <input type="number" step="0.0001" min="0" max="100" name="team_max_total_rate" class="form-control" value="{{ old('team_max_total_rate', 0) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label d-block">Flags</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="p_active" @checked(old('is_active', true))>
                    <label class="form-check-label" for="p_active">Active</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="p_def" @checked(old('is_default'))>
                    <label class="form-check-label" for="p_def">Default</label>
                </div>
            </div>
            <div class="col-md-8">
                <label class="form-label">Team enabled</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="team_enabled" value="1" id="team_en" @checked(old('team_enabled'))>
                    <label class="form-check-label" for="team_en">Pay team/upline commissions</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" maxlength="1000" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary">Publish policy</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header card-header-bg text-white"><h6 class="mb-0">All policies</h6></div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-bordered align-middle">
            <thead>
                <tr><th>#</th><th>Name</th><th>Key</th><th>Rate</th><th>Flat</th><th>Basis</th><th>Team</th><th>Version</th><th>Priority</th><th>Window</th><th>Active</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($policies as $policy)
                    <tr>
                        <td>{{ $policy->id }}</td>
                        <td>{{ $policy->name }} @if($policy->is_default)<span class="badge bg-dark">default</span>@endif</td>
                        <td>{{ $policy->policy_key ?? '—' }}</td>
                        <td>{{ rtrim(rtrim(number_format($policy->rate, 4), '0'), '.') }}%</td>
                        <td>{{ number_format((float) $policy->fixed_amount, 2) }}</td>
                        <td>{{ $policy->basis }}</td>
                        <td>{!! $policy->team_enabled ? 'on ('.$policy->team_max_depth.' deep)' : '<span class="text-muted">off</span>' !!}</td>
                        <td>v{{ $policy->version }}</td>
                        <td>{{ $policy->priority }}</td>
                        <td>
                            {{ optional($policy->effective_from)->format('Y-m-d') ?? '—' }}
                            →
                            {{ optional($policy->effective_to)->format('Y-m-d') ?? '—' }}
                        </td>
                        <td>{!! $policy->is_active ? '<span class="badge bg-success">active</span>' : '<span class="badge bg-secondary">off</span>' !!}</td>
                        <td class="d-flex gap-1">
                            <form method="POST" action="{{ route('admin.sales-agents.policies.toggle', $policy->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $policy->is_active ? 'Disable' : 'Enable' }}</button></form>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#revise-{{ $policy->id }}">New version</button>
                        </td>
                    </tr>
                    <tr class="collapse" id="revise-{{ $policy->id }}">
                        <td colspan="12">
                            <form action="{{ route('admin.sales-agents.policies.update', $policy->id) }}" method="POST" class="row g-2">
                                @csrf
                                @method('PUT')
                                <div class="col-md-3"><input name="name" class="form-control form-control-sm" value="{{ $policy->name }}" required></div>
                                <div class="col-md-2"><input type="number" step="0.0001" name="rate" class="form-control form-control-sm" value="{{ $policy->rate }}" required></div>
                                <div class="col-md-2"><input type="number" step="0.01" name="fixed_amount" class="form-control form-control-sm" value="{{ $policy->fixed_amount }}"></div>
                                <div class="col-md-3">
                                    <select name="basis" class="form-select form-select-sm">
                                        @foreach ($bases as $basis)
                                            <option value="{{ $basis }}" @selected($policy->basis === $basis)>{{ $basisLabels[$basis] ?? $basis }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2"><input type="number" name="priority" class="form-control form-control-sm" value="{{ $policy->priority }}"></div>
                                <div class="col-md-2"><input type="number" step="0.01" name="min_order_value" class="form-control form-control-sm" value="{{ $policy->min_order_value }}" placeholder="min order"></div>
                                <div class="col-md-2"><input type="number" step="0.01" name="max_commission" class="form-control form-control-sm" value="{{ $policy->max_commission }}" placeholder="max comm"></div>
                                <div class="col-md-2"><input type="number" step="0.0001" name="team_rate" class="form-control form-control-sm" value="{{ $policy->team_rate }}" placeholder="team %"></div>
                                <div class="col-md-2"><input type="number" name="team_max_depth" class="form-control form-control-sm" value="{{ $policy->team_max_depth }}" placeholder="depth"></div>
                                <div class="col-md-2"><input type="number" step="0.0001" name="team_max_total_rate" class="form-control form-control-sm" value="{{ $policy->team_max_total_rate }}" placeholder="cap %"></div>
                                <div class="col-md-2"><input type="hidden" name="include_tax" value="0"><input type="checkbox" name="include_tax" value="1" @checked($policy->include_tax)> tax</div>
                                <div class="col-md-2"><input type="hidden" name="include_delivery_fee" value="0"><input type="checkbox" name="include_delivery_fee" value="1" @checked($policy->include_delivery_fee)> delivery</div>
                                <div class="col-md-2"><input type="hidden" name="allow_self_purchase" value="0"><input type="checkbox" name="allow_self_purchase" value="1" @checked($policy->allow_self_purchase)> self</div>
                                <div class="col-md-2"><input type="hidden" name="allow_guest_orders" value="0"><input type="checkbox" name="allow_guest_orders" value="1" @checked($policy->allow_guest_orders)> guest</div>
                                <div class="col-md-2"><input type="hidden" name="team_enabled" value="0"><input type="checkbox" name="team_enabled" value="1" @checked($policy->team_enabled)> team</div>
                                <div class="col-md-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(true)> active</div>
                                <div class="col-md-2"><button class="btn btn-sm btn-primary">Publish v{{ $policy->version + 1 }}</button></div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center text-muted">No policies yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
