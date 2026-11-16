<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $staff->name ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $staff->email ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $staff->phone ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Role / position</label>
        <select name="role" class="form-select" required>
            @foreach($roles as $key => $label)
                <option value="{{ $key }}" @selected(old('role', $staff->role ?? 'admin') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Password {{ $staff ? '(leave blank to keep)' : '' }}</label>
        <input type="password" name="password" class="form-control" {{ $staff ? '' : 'required' }}>
    </div>
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select">
            <option value="1" @selected(old('is_active', $staff->is_active ?? true))>Active</option>
            <option value="0" @selected(!old('is_active', $staff->is_active ?? true))>Inactive</option>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Extra permissions (comma separated)</label>
        <input type="text" name="permissions[]" class="form-control" value="{{ old('permissions', is_array($staff->permissions ?? null) ? implode(',', $staff->permissions) : '') }}">
        <small class="text-muted">Optional granular permissions in addition to the role.</small>
    </div>
</div>
