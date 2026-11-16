@php($agent = $agent ?? new App\Models\SalesRep())
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Name</label>
        <input name="name" value="{{ old('name', $agent->name) }}" class="form-control" required maxlength="150">
        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email', $agent->email) }}" class="form-control" maxlength="150">
        @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Phone</label>
        <input name="phone" value="{{ old('phone', $agent->phone) }}" class="form-control" maxlength="50">
        @error('phone')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Referral code</label>
        <input name="code" value="{{ old('code', $agent->code) }}" class="form-control" maxlength="50" placeholder="Auto-generated if blank">
        @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Linked staff user ID</label>
        <input type="number" name="user_id" value="{{ old('user_id', $agent->user_id) }}" class="form-control" min="1">
        @error('user_id')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Sponsor (upline)</label>
        <select name="sponsor_id" class="form-select">
            <option value="">— None —</option>
            @foreach ($agents as $option)
                <option value="{{ $option->id }}" @selected((int) old('sponsor_id', $agent->sponsor_id) === $option->id)>{{ $option->name }} ({{ $option->code }})</option>
            @endforeach
        </select>
        @error('sponsor_id')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Region</label>
        <select name="region_id" class="form-select">
            <option value="">— None —</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((int) old('region_id', $agent->region_id) === $region->id)>{{ $region->name }}</option>
            @endforeach
        </select>
        @error('region_id')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Commission %</label>
        <input type="number" step="0.01" min="0" max="100" name="commission_rate" value="{{ old('commission_rate', $agent->commission_rate ?? 0) }}" class="form-control" required>
        @error('commission_rate')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Monthly target</label>
        <input type="number" step="0.01" min="0" name="monthly_target" value="{{ old('monthly_target', $agent->monthly_target ?? 0) }}" class="form-control">
        @error('monthly_target')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Payout method</label>
        <input name="payout_method" value="{{ old('payout_method', $agent->payout_method) }}" class="form-control" maxlength="50" placeholder="mobile_money, bank…">
    </div>
    <div class="col-md-4">
        <label class="form-label">Payout account name</label>
        <input name="payout_account_name" value="{{ old('payout_account_name', $agent->payout_account_name) }}" class="form-control" maxlength="150">
    </div>
    <div class="col-md-4">
        <label class="form-label">Payout account number</label>
        <input name="payout_account_number" value="{{ old('payout_account_number') }}" class="form-control" maxlength="150" autocomplete="off" placeholder="{{ $agent->exists ? 'Leave blank to keep current' : '' }}">
    </div>

    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check d-inline-block me-4">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $agent->is_active ?? false))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <input type="hidden" name="customer_confirmation_required" value="0">
        <div class="form-check d-inline-block">
            <input class="form-check-input" type="checkbox" name="customer_confirmation_required" value="1" id="ccr" @checked(old('customer_confirmation_required', $agent->customer_confirmation_required ?? true))>
            <label class="form-check-label" for="ccr">Require customer confirmation</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="2" class="form-control" maxlength="2000">{{ old('notes', $agent->notes) }}</textarea>
    </div>
</div>
