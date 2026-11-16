@php($rep = $rep ?? new App\Models\SalesRep())
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Name</label>
        <input name="name" value="{{ old('name', $rep->name) }}" class="form-control" required maxlength="150">
        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email', $rep->email) }}" class="form-control" maxlength="150">
        @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Referral code</label>
        <input name="code" value="{{ old('code', $rep->code) }}" class="form-control" maxlength="50" placeholder="Auto-generated if blank">
        @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Linked staff user ID</label>
        <input type="number" name="user_id" value="{{ old('user_id', $rep->user_id) }}" class="form-control" min="1">
        @error('user_id')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Commission %</label>
        <input type="number" step="0.01" min="0" max="100" name="commission_rate" value="{{ old('commission_rate', $rep->commission_rate ?? 0) }}" class="form-control" required>
        @error('commission_rate')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Monthly target</label>
        <input type="number" step="0.01" min="0" name="monthly_target" value="{{ old('monthly_target', $rep->monthly_target ?? 0) }}" class="form-control">
        @error('monthly_target')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $rep->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>
