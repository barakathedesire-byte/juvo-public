@extends('vendor.layouts.master')

@section('title', 'Coupons')

@section('content')
    <h4 class="mb-3"><i class="fas fa-ticket me-2" style="color:#f68b1e;"></i>Coupons</h4>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white"><i class="fas fa-plus me-1"></i>New coupon</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vendor.coupons.store') }}" id="couponForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" class="form-control text-uppercase" required maxlength="40" value="{{ old('code') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" maxlength="150" value="{{ old('title') }}" placeholder="e.g. Launch offer">
                        </div>
                        <div class="row g-2">
                            <div class="col-6 mb-3">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select" id="couponType">
                                    <option value="percentage" {{ old('type') === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                    <option value="fixed" {{ old('type') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Value</label>
                                <input type="number" step="0.01" min="0.01" name="discount" class="form-control" required value="{{ old('discount') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Min order value</label>
                            <input type="number" step="0.01" min="0" name="min_order_value" class="form-control" value="{{ old('min_order_value', 0) }}">
                        </div>
                        <div class="row g-2">
                            <div class="col-6 mb-3">
                                <label class="form-label">Usage limit</label>
                                <input type="number" min="1" name="usage_limit" class="form-control" value="{{ old('usage_limit') }}">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Per customer</label>
                                <input type="number" min="1" name="per_customer_limit" class="form-control" value="{{ old('per_customer_limit') }}">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6 mb-3">
                                <label class="form-label">Starts</label>
                                <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Expires</label>
                                <input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="couponActive" checked>
                            <label class="form-check-label" for="couponActive">Active</label>
                        </div>
                        <button class="btn btn-success w-100">Create coupon</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white">Your coupons</h6>
                </div>
                <div class="card-body">
                    @if ($coupons->isEmpty())
                        <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>No coupons yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>Min order</th>
                                        <th>Used</th>
                                        <th>Window</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($coupons as $coupon)
                                        <tr>
                                            <td>
                                                <span class="fw-semibold">{{ $coupon->code }}</span>
                                                @if ($coupon->title)
                                                    <div class="small text-muted">{{ $coupon->title }}</div>
                                                @endif
                                            </td>
                                            <td>{{ ucfirst($coupon->type) }}</td>
                                            <td>{{ $coupon->type === 'percentage' ? rtrim(rtrim(number_format((float) $coupon->discount, 2), '0'), '.').'%' : number_format((float) $coupon->discount, 2) }}</td>
                                            <td>{{ number_format((float) $coupon->min_order_value, 2) }}</td>
                                            <td>{{ $coupon->used_count }}@if($coupon->usage_limit) / {{ $coupon->usage_limit }}@endif</td>
                                            <td class="small">
                                                {{ $coupon->starts_at?->format('d M Y') ?? '—' }} &rarr; {{ $coupon->expires_at?->format('d M Y') ?? '—' }}
                                            </td>
                                            <td>
                                                <span class="badge text-bg-{{ $coupon->isActive() ? 'success' : 'secondary' }}">
                                                    {{ $coupon->isActive() ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <form method="POST" action="{{ route('vendor.coupons.update', $coupon->id) }}" class="d-inline">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="code" value="{{ $coupon->code }}">
                                                    <input type="hidden" name="title" value="{{ $coupon->title }}">
                                                    <input type="hidden" name="type" value="{{ $coupon->type }}">
                                                    <input type="hidden" name="discount" value="{{ $coupon->discount }}">
                                                    <input type="hidden" name="min_order_value" value="{{ $coupon->min_order_value }}">
                                                    <input type="hidden" name="is_active" value="{{ $coupon->is_active ? 0 : 1 }}">
                                                    <button class="btn btn-sm btn-outline-secondary">{{ $coupon->is_active ? 'Disable' : 'Enable' }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('vendor.coupons.destroy', $coupon->id) }}" class="d-inline" onsubmit="return confirm('Delete this coupon?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
