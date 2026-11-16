@php($granted = $granted ?? [])
@php($policies = $policies ?? collect())

<div class="card">
    <div class="card-header"><h6 class="mb-0">Role</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Role name</label>
                <input name="name" class="form-control" value="{{ old('name', $role->name) }}"
                       {{ in_array($role->name, ['super_admin', 'admin'] ?? [], true) ? 'readonly' : '' }} required>
                <div class="form-text">Lowercase letters, numbers and underscores.</div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Permission matrix</h6>
        <div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm-check').forEach(c=>c.checked=true)">Select all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm-check').forEach(c=>c.checked=false)">Clear</button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            @foreach($modules as $module => $meta)
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="border rounded p-2 h-100">
                        <div class="fw-semibold mb-2">{{ $meta['label'] }}</div>
                        @foreach($meta['actions'] as $action => $label)
                            @php($perm = $module.'.'.$action)
                            <div class="form-check">
                                <input class="form-check-input perm-check" type="checkbox" name="permissions[]"
                                       id="perm_{{ $perm }}" value="{{ $perm }}"
                                       {{ in_array($perm, old('permissions', $granted), true) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="perm_{{ $perm }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Decision authority</h6></div>
    <div class="card-body">
        <p class="text-muted small">Choose how this role may act on sensitive operations. Thresholds are stored server-side and enforced in the backend.</p>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Operation</th><th style="width:220px">Authority</th><th style="width:180px">Limit</th></tr></thead>
                <tbody>
                    @foreach($abilities as $ability => $definition)
                        @php($current = old("policies.$ability.mode", $policies[$ability]->mode ?? 'allowed'))
                        @php($limits = $policies[$ability]->limits ?? [])
                        @php($limitValue = old("policies.$ability.limit", $definition['limit_key'] ? ($limits[$definition['limit_key']] ?? '') : ''))
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $definition['label'] }}</div>
                                <code class="small text-muted">{{ $ability }}</code>
                            </td>
                            <td>
                                <select name="policies[{{ $ability }}][mode]" class="form-select form-select-sm">
                                    <option value="allowed" {{ $current === 'allowed' ? 'selected' : '' }}>Allowed</option>
                                    <option value="limited" {{ $current === 'limited' ? 'selected' : '' }}>Allowed within limits</option>
                                    <option value="requires_approval" {{ $current === 'requires_approval' ? 'selected' : '' }}>Requires approval</option>
                                    <option value="prohibited" {{ $current === 'prohibited' ? 'selected' : '' }}>Prohibited</option>
                                </select>
                            </td>
                            <td>
                                @if($definition['limit_key'])
                                    <input type="number" step="0.01" min="0" class="form-control form-select-sm"
                                           name="policies[{{ $ability }}][limit]" value="{{ $limitValue }}" placeholder="No limit">
                                @else
                                    <span class="text-muted small">n/a</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
