@extends('agent.layouts.agent')

@section('content')
<div class="row g-3">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Profile</h6>
                <form action="{{ route('agent.profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input name="name" class="form-control" value="{{ old('name', $agent->name) }}" required>
                            @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $agent->email) }}">
                            @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input name="phone" class="form-control" value="{{ old('phone', $agent->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Referral code</label>
                            <input class="form-control" value="{{ $agent->code }}" readonly>
                        </div>

                        <div class="col-12"><hr><h6 class="mb-0">Payout details</h6></div>
                        <div class="col-md-4">
                            <label class="form-label">Method</label>
                            <input name="payout_method" class="form-control" value="{{ old('payout_method', $agent->payout_method) }}" placeholder="mobile_money / bank">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account name</label>
                            <input name="payout_account_name" class="form-control" value="{{ old('payout_account_name', $agent->payout_account_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account number</label>
                            <input name="payout_account_number" class="form-control" value="{{ old('payout_account_number') }}" placeholder="{{ $agent->payout_account_number ? 'Leave blank to keep current' : '' }}" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary">Save changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6>Account</h6>
                <table class="table table-sm mb-0">
                    <tr><th>Status</th><td>{{ ucfirst($agent->status) }}</td></tr>
                    <tr><th>Verification</th><td>{{ ucfirst($agent->verification_status) }}</td></tr>
                    <tr><th>Payout account</th><td>{{ ucfirst($agent->payout_status ?? 'unset') }}</td></tr>
                    <tr><th>Commission</th><td>{{ $agent->commission_rate }}%</td></tr>
                    <tr><th>Currency</th><td>{{ $agent->currency }}</td></tr>
                    <tr><th>Region</th><td>{{ $agent->region->name ?? '—' }}</td></tr>
                </table>
                @if ($agent->payout_status !== 'verified')
                    <div class="alert alert-warning small mt-3 mb-0">Your payout account is not verified yet. Payouts are disabled until our team verifies it.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
