@php($readOnly = $readOnly ?? false)

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2" style="background:var(--juvo-primary,#f68b1e)">
                <strong class="text-white"><i class="fas fa-lock me-1"></i>Built-in tabs</strong>
            </div>
            <div class="card-body" style="max-height:560px;overflow:auto">
                <p class="small text-muted">Shipped by the application and managed in code. Listed here for visibility —
                    only custom tabs can be added, reordered or hidden.</p>
                @foreach ($groups as $group)
                    <div class="mb-3">
                        <div class="small fw-bold text-uppercase text-muted mb-1">{{ $group }}</div>
                        @foreach ($builtInTabs as $tab)
                            @if ($tab['group'] === $group)
                                <div class="d-flex align-items-center small py-1 border-bottom">
                                    <i class="{{ $tab['icon'] }} me-2 text-muted"></i>
                                    <span>{{ $tab['label'] }}</span>
                                    <span class="badge bg-secondary ms-auto">{{ $tab['key'] }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        @unless ($readOnly)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header py-2" style="background:var(--juvo-primary,#f68b1e)">
                    <strong class="text-white"><i class="fas fa-plus me-1"></i>Add a custom tab</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.admin-settings.tabs.store') }}" class="row g-2">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="t-label">Label</label>
                            <input type="text" class="form-control form-control-sm" id="t-label" name="label"
                                   value="{{ old('label') }}" placeholder="Fulfilment board" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1" for="t-url">URL</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="t-url" name="url"
                                   value="{{ old('url') }}" placeholder="/admin/orders or https://…" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1" for="t-icon">Icon (Font Awesome class)</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="t-icon" name="icon"
                                   value="{{ old('icon', 'fas fa-link') }}" placeholder="fas fa-truck">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1" for="t-group">Group</label>
                            <input type="text" class="form-control form-control-sm" id="t-group" name="group"
                                   value="{{ old('group', 'Custom') }}" list="group-suggestions" required>
                            <datalist id="group-suggestions">
                                @foreach ($groups as $group)
                                    <option value="{{ $group }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="t-permission">Permission (optional)</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="t-permission"
                                   name="permission" value="{{ old('permission') }}" placeholder="finance.view — leave empty for all staff">
                            <small class="text-muted">Hide the tab from staff without this permission.</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1" for="t-sort">Sort</label>
                            <input type="number" class="form-control form-control-sm" id="t-sort" name="sort"
                                   value="{{ old('sort', 100) }}" min="0" max="9999">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="t-enabled" name="is_enabled" value="1"
                                       @checked(old('is_enabled', true))>
                                <label class="form-check-label small" for="t-enabled">Visible</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-sm text-white" style="background:var(--juvo-primary,#f68b1e)">
                                <i class="fas fa-plus me-1"></i>Add tab
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endunless

        <div class="card border-0 shadow-sm">
            <div class="card-header py-2" style="background:var(--juvo-primary,#f68b1e)">
                <strong class="text-white"><i class="fas fa-table-columns me-1"></i>Custom tabs</strong>
            </div>
            <div class="card-body">
                @if ($customTabs->isEmpty())
                    <p class="small text-muted mb-0">No custom tabs yet.{{ $readOnly ? '' : ' Add one above to extend the sidebar.' }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-uppercase text-muted">
                                    <th>Label</th>
                                    <th>URL</th>
                                    <th>Group</th>
                                    <th>Permission</th>
                                    <th>Sort</th>
                                    <th>Visible</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customTabs as $tab)
                                    <tr>
                                        <td><i class="{{ $tab->icon ?: 'fas fa-link' }} me-2 text-muted"></i>{{ $tab->label }}</td>
                                        <td class="font-monospace small">{{ $tab->url }}</td>
                                        <td>{{ $tab->group }}</td>
                                        <td class="font-monospace small">{{ $tab->permission ?: '—' }}</td>
                                        <td>{{ $tab->sort }}</td>
                                        <td>
                                            @if ($tab->is_enabled)
                                                <span class="badge" style="background:#15803d">visible</span>
                                            @else
                                                <span class="badge bg-secondary">hidden</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            @unless ($readOnly)
                                                <button type="button" class="btn btn-sm btn-outline-primary tab-edit-btn"
                                                        data-target="tab-edit-{{ $tab->id }}" title="Edit">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                            @endunless
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ \Illuminate\Support\Str::startsWith($tab->url, ['http://', 'https://']) ? $tab->url : url($tab->url) }}"
                                               title="Open tab" target="_blank">
                                                <i class="fas fa-arrow-up-right-from-square"></i>
                                            </a>
                                            @unless ($readOnly)
                                                <form method="POST" action="{{ route('admin.admin-settings.tabs.toggle', $tab) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Toggle visibility">
                                                        <i class="fas fa-eye{{ $tab->is_enabled ? '' : '-slash' }}"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.admin-settings.tabs.destroy', $tab) }}"
                                                      class="d-inline" data-confirm="Delete the custom tab “{{ $tab->label }}”?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                    @unless ($readOnly)
                                        <tr class="d-none" id="tab-edit-{{ $tab->id }}">
                                            <td colspan="7" class="bg-light">
                                                <form method="POST" action="{{ route('admin.admin-settings.tabs.update', $tab) }}" class="row g-2 p-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="col-md-3">
                                                        <label class="form-label small mb-1">Label</label>
                                                        <input type="text" class="form-control form-control-sm" name="label"
                                                               value="{{ old('label', $tab->label) }}" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small mb-1">URL</label>
                                                        <input type="text" class="form-control form-control-sm font-monospace" name="url"
                                                               value="{{ old('url', $tab->url) }}" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Icon</label>
                                                        <input type="text" class="form-control form-control-sm font-monospace" name="icon"
                                                               value="{{ old('icon', $tab->icon) }}">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Group</label>
                                                        <input type="text" class="form-control form-control-sm" name="group"
                                                               value="{{ old('group', $tab->group) }}" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Permission</label>
                                                        <input type="text" class="form-control form-control-sm font-monospace" name="permission"
                                                               value="{{ old('permission', $tab->permission) }}">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Sort</label>
                                                        <input type="number" class="form-control form-control-sm" name="sort"
                                                               value="{{ old('sort', $tab->sort) }}" min="0" max="9999">
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-center">
                                                        <div class="form-check mt-2">
                                                            <input class="form-check-input" type="checkbox" name="is_enabled" value="1"
                                                                   @checked(old('is_enabled', $tab->is_enabled))>
                                                            <label class="form-check-label small">Visible</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 d-flex align-items-center gap-2 mt-2">
                                                        <button type="submit" class="btn btn-sm text-white"
                                                                style="background:var(--juvo-primary,#f68b1e)">
                                                            <i class="fas fa-check me-1"></i>Save changes
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary tab-cancel-btn"
                                                                data-target="tab-edit-{{ $tab->id }}">Cancel</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    @endunless
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@unless ($readOnly)
    <script>
        document.querySelectorAll('.tab-edit-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.dataset.target).classList.toggle('d-none');
            });
        });
        document.querySelectorAll('.tab-cancel-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.dataset.target).classList.add('d-none');
            });
        });
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                if (!window.confirm(form.dataset.confirm)) e.preventDefault();
            });
        });
    </script>
@endunless
