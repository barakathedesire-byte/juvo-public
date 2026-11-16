@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
        <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.vendors.register_new_vendor') }}</h6>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.vendors.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">{{ __('cms.vendors.vendor_name') }}</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                       value="{{ old('name') }}" maxlength="255">
                @error('name')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('cms.vendors.vendor_email') }}</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                       value="{{ old('email') }}" maxlength="255">
                @error('email')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">{{ __('cms.vendors.phone_optional') }}</label>
                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                       value="{{ old('phone') }}" maxlength="20">
                @error('phone')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">{{ __('cms.vendors.password') }}</label>
                <input type="password" name="password" 
                       class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                @error('password')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">{{ __('cms.vendors.confirm_password') }}</label>
                <input type="password" name="password_confirmation" 
                       class="form-control @error('password_confirmation') is-invalid @enderror" autocomplete="new-password">
                @error('password_confirmation')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">{{ __('cms.vendors.status') }}</label>
                <select name="status" class="form-select @error('status') is-invalid @enderror">
                    <option value="active" {{ old('status')=='active' ? 'selected' : '' }}>{{ __('cms.vendors.active') }}</option>
                    <option value="inactive" {{ old('status')=='inactive' ? 'selected' : '' }}>{{ __('cms.vendors.inactive') }}</option>
                    <option value="banned" {{ old('status')=='banned' ? 'selected' : '' }}>{{ __('cms.vendors.banned') }}</option>
                </select>
                @error('status')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="commission_rate" class="form-label">Commission rate (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="commission_rate" id="commission_rate" class="form-control @error('commission_rate') is-invalid @enderror" value="{{ old('commission_rate') }}" placeholder="Platform default">
                    @error('commission_rate')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="minimum_payout" class="form-label">Minimum payout</label>
                    <input type="number" step="0.01" min="0" name="minimum_payout" id="minimum_payout" class="form-control @error('minimum_payout') is-invalid @enderror" value="{{ old('minimum_payout') }}" placeholder="Optional">
                    @error('minimum_payout')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-success">{{ __('cms.vendors.register_button') }}</button>
            <a href="{{ route('admin.vendors.index') }}" class="btn btn-secondary">{{ __('cms.vendors.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
