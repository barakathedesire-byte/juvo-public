@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0"><i class="fas fa-terminal me-2"></i>Admin Terminal</h4>
            <small class="text-muted">Shell, artisan, code edits and pentest helpers. Server PHP {{ $serverPhp }} · {{ $root }}</small>
        </div>
        <div class="alert alert-warning py-1 px-3 mb-0" style="max-width:520px;font-size:.8rem;">
            <i class="fas fa-triangle-exclamation me-1"></i>
            This runs as the web user with real side effects. Every command, file read and file write is recorded in
            <a href="{{ route('admin.audit-logs.index') }}">the audit log</a>. Writes require confirming the target file first.
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <strong><i class="fas fa-lock me-1"></i>Terminal access</strong>
                    <div class="small text-muted">
                        @if(! $configured)
                            No PIN has been configured. An administrator must set one before the terminal can be used.
                        @elseif($unlocked)
                            Unlocked for this session · locks automatically in <span id="lockCountdown">{{ $remainingSeconds }}</span>s.
                        @else
                            The terminal is locked. Enter the administrator PIN to start a session.
                        @endif
                    </div>
                </div>
                @if($configured && $unlocked)
                    <button class="btn btn-sm btn-outline-secondary" id="lockBtn"><i class="fas fa-lock me-1"></i>Lock now</button>
                @endif
            </div>

            @if($configured && ! $unlocked)
                <div class="row g-2 mt-3" id="unlockForm">
                    <div class="col-auto">
                        <input type="password" id="pinInput" class="form-control font-monospace" inputmode="numeric"
                               autocomplete="off" placeholder="Administrator PIN" style="max-width:220px">
                    </div>
                    <div class="col-auto">
                        <button class="btn text-white" style="background:var(--juvo-primary,#f68b1e)" id="unlockBtn">
                            <i class="fas fa-unlock me-1"></i>Unlock terminal
                        </button>
                    </div>
                    <div class="col-12"><small class="text-danger d-none" id="unlockError"></small></div>
                </div>
            @endif

            @if($canConfigurePin)
                <hr>
                <div class="small text-muted mb-2">Administrator PIN (only staff with permission to manage admin settings can change this).</div>
                <form method="POST" action="{{ route('admin.terminal.pin.update') }}" class="row g-2 align-items-end" id="pinForm">
                    @csrf
                    <input type="hidden" name="_method" id="pinFormMethod" value="POST">
                    @if($configured)
                        <div class="col-auto">
                            <label class="form-label small mb-1">Current PIN</label>
                            <input type="password" name="current_pin" class="form-control font-monospace" inputmode="numeric"
                                   autocomplete="current-password" placeholder="Current" style="max-width:200px" required>
                        </div>
                    @endif
                    <div class="col-auto">
                        <label class="form-label small mb-1">{{ $configured ? 'New PIN' : 'Set PIN' }}</label>
                        <input type="password" name="pin" class="form-control font-monospace" inputmode="numeric"
                               autocomplete="new-password" placeholder="6–12 digits" style="max-width:200px" required>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-1">Confirm</label>
                        <input type="password" name="pin_confirmation" class="form-control font-monospace" inputmode="numeric"
                               autocomplete="new-password" placeholder="Repeat" style="max-width:200px" required>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-primary" type="submit"
                                onclick="document.getElementById('pinFormMethod').value='POST';document.getElementById('pinForm').setAttribute('action','{{ route('admin.terminal.pin.update') }}');">Save PIN</button>
                    </div>
                    @if($configured)
                        <div class="col-auto">
                            <button class="btn btn-outline-danger" type="submit" formnovalidate
                                    onclick="if(!confirm('Remove the terminal PIN? The terminal will be inaccessible until a new PIN is set.')){return false;}document.getElementById('pinFormMethod').value='DELETE';document.getElementById('pinForm').setAttribute('action','{{ route('admin.terminal.pin.destroy') }}');">Remove PIN</button>
                        </div>
                    @endif
                </form>

            @endif
        </div>
    </div>

    @if(! $configured)
        <div class="alert alert-secondary">Set an administrator PIN above to enable the terminal.</div>
    @elseif(! $unlocked)
        <div class="alert alert-secondary">Unlock the terminal above to run commands and edit files.</div>
    @else
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <ul class="nav nav-pills mb-3" id="termModeTabs" role="tablist">
                @foreach (array_keys($presets) as $mode)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $mode === 'shell' ? 'active' : '' }}"
                                id="mode-{{ $mode }}-tab" data-bs-toggle="pill"
                                data-bs-target="#mode-{{ $mode }}" type="button" role="tab"
                                data-mode="{{ $mode }}">{{ ucfirst($mode) }}</button>
                    </li>
                @endforeach
            </ul>

            <div class="d-flex gap-2 mb-2">
                <input type="text" id="cmdInput" class="form-control font-monospace"
                       placeholder="Type a shell command…" autocomplete="off">
                <select id="cmdTimeout" class="form-select" style="max-width:110px" title="Timeout (seconds)">
                    @foreach ([10, 30, 60, 120] as $t)
                        <option value="{{ $t }}" {{ $t === 30 ? 'selected' : '' }}>{{ $t }}s</option>
                    @endforeach
                </select>
                <button class="btn text-white" style="background:var(--juvo-primary,#f68b1e)" id="cmdRunBtn">
                    <i class="fas fa-play me-1"></i>Run
                </button>
                <button class="btn btn-outline-secondary" id="cmdClearBtn" title="Clear output">
                    <i class="fas fa-eraser"></i>
                </button>
            </div>

            <div class="tab-content mt-1">
                @foreach ($presets as $mode => $items)
                    <div class="tab-pane fade {{ $mode === 'shell' ? 'show active' : '' }}"
                         id="mode-{{ $mode }}" role="tabpanel" data-pane="{{ $mode }}">
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($items as $preset)
                                <button class="btn btn-sm btn-outline-secondary preset-btn"
                                        data-mode="{{ $mode }}"
                                        data-command="{{ $preset['command'] }}">{{ $preset['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <pre id="termOut" class="mt-3 mb-0 font-monospace p-3 rounded"
                 style="background:#12161f;color:#d5dbe8;min-height:220px;max-height:420px;overflow:auto;font-size:.8rem;white-space:pre-wrap;">Terminal ready. Output appears here.</pre>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2"
                     style="background:var(--juvo-primary,#f68b1e)">
                    <strong class="text-white"><i class="fas fa-folder-tree me-1"></i>Files</strong>
                    <span class="badge bg-dark text-white" id="treePath">./</span>
                </div>
                <div class="card-body p-2" id="treeBody" style="max-height:340px;overflow:auto;"></div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2 flex-wrap gap-2"
                     style="background:var(--juvo-primary,#f68b1e)">
                    <strong class="text-white"><i class="fas fa-file-code me-1"></i>Editor</strong>
                    <span class="badge bg-dark text-white" id="fileMeta">no file loaded</span>
                </div>
                <div class="card-body p-2">
                    <div class="d-flex gap-2 mb-2 flex-wrap">
                        <input type="text" id="pathInput" class="form-control font-monospace" style="font-size:.82rem"
                               placeholder="path/to/file.php" autocomplete="off">
                        <button class="btn btn-outline-secondary btn-sm" id="openBtn"><i class="fas fa-folder-open me-1"></i>Open</button>
                        <button class="btn btn-outline-secondary btn-sm" id="newFileBtn"><i class="fas fa-file-circle-plus me-1"></i>New file</button>
                    </div>

                    <textarea id="editor" class="form-control font-monospace" rows="14"
                              style="font-size:.8rem;background:#12161f;color:#d5dbe8;border-color:#2a3142"
                              placeholder="Open a file to edit…" spellcheck="false"></textarea>

                    <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                        <span class="badge" id="confirmBadge" style="background:#6b7280">target not confirmed</span>
                        <span class="badge" id="dirtyBadge" style="background:#6b7280">saved</span>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-sm btn-outline-warning" id="confirmBtn" disabled>
                                <i class="fas fa-fingerprint me-1"></i>Confirm target file
                            </button>
                            <button class="btn btn-sm text-white" id="saveBtn" style="background:var(--juvo-primary,#f68b1e)" disabled>
                                <i class="fas fa-floppy-disk me-1"></i>Save file
                            </button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1">
                        Save stays disabled until you confirm the target file; the server checks that confirmation and the
                        content hash it loaded, so a file you never opened (or one that changed underneath you) cannot be overwritten.
                    </small>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection

@section('css')
    <style>
        #content .container { max-width: 100%; }
        .file-entry { cursor:pointer; padding:.18rem .35rem; border-radius:.35rem; font-size:.82rem; display:flex; gap:.4rem; align-items:center; }
        .file-entry:hover { background: rgba(246,139,30,.12); }
        .file-entry.active { background: var(--juvo-primary,#f68b1e); color:#fff; }
    </style>
@endsection

@section('js')
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const unlockUrl = @json(route('admin.terminal.unlock'));
            const lockUrl = @json(route('admin.terminal.lock'));
            const unlockBtn = document.getElementById('unlockBtn');
            const pinInput = document.getElementById('pinInput');
            const unlockError = document.getElementById('unlockError');
            const lockBtn = document.getElementById('lockBtn');
            const countdown = document.getElementById('lockCountdown');

            async function post(url, payload) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify(payload || {})
                });
                const data = await res.json().catch(() => ({ ok: false, message: 'Invalid response from server.' }));
                if (!res.ok || data.ok === false) {
                    throw new Error(data.message || ('Request failed (' + res.status + ').'));
                }
                return data;
            }

            if (unlockBtn) {
                const doUnlock = async () => {
                    unlockError.classList.add('d-none');
                    unlockBtn.disabled = true;
                    try {
                        await post(unlockUrl, { pin: pinInput.value });
                        window.location.reload();
                    } catch (e) {
                        unlockError.textContent = e.message;
                        unlockError.classList.remove('d-none');
                        pinInput.focus();
                        pinInput.select();
                    } finally {
                        unlockBtn.disabled = false;
                    }
                };
                unlockBtn.addEventListener('click', doUnlock);
                pinInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doUnlock(); } });
                pinInput.focus();
            }

            if (lockBtn) {
                lockBtn.addEventListener('click', async () => {
                    try { await post(lockUrl, {}); } catch (e) { /* fall through to reload */ }
                    window.location.reload();
                });
            }

            if (countdown) {
                let remaining = parseInt(countdown.textContent, 10) || 0;
                const tick = () => {
                    if (remaining <= 0) { window.location.reload(); return; }
                    remaining -= 1;
                    countdown.textContent = remaining;
                    setTimeout(tick, 1000);
                };
                if (remaining > 0) setTimeout(tick, 1000);
            }
        })();

        (function () {
            if (!document.getElementById('cmdInput')) return;
            const endpoints = {
                exec: @json(route('admin.terminal.exec')),
                read: @json(route('admin.terminal.read')),
                write: @json(route('admin.terminal.write')),
                tree: @json(route('admin.terminal.tree'))
            };
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const NEW_TOKEN = '__NEW__';

            const state = {
                mode: 'shell',
                loadedPath: null,
                loadedSha: null,
                isNew: false,
                confirmedPath: null,
                dirty: false
            };

            const $ = (id) => document.getElementById(id);
            const out = $('termOut');

            async function api(url, payload) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json().catch(() => ({ ok: false, message: 'Invalid response from server.' }));
                if (!res.ok || data.ok === false) {
                    throw new Error(data.message || ('Request failed (' + res.status + ').'));
                }
                return data;
            }

            function logLine(text, kind) {
                if (out.dataset.fresh !== '1') { out.textContent = ''; out.dataset.fresh = '1'; }
                const span = document.createElement('span');
                span.style.color = kind === 'err' ? '#ff8f8f' : kind === 'head' ? '#ffc46b' : '#d5dbe8';
                span.textContent = text + '\n';
                out.appendChild(span);
                out.scrollTop = out.scrollHeight;
            }

            function setMode(mode) {
                state.mode = mode;
                $('cmdInput').placeholder = mode === 'artisan'
                    ? 'Type artisan arguments, e.g. migrate:status …'
                    : 'Type a shell command…';
            }

            document.querySelectorAll('#termModeTabs [data-mode]').forEach((btn) => {
                btn.addEventListener('click', () => setMode(btn.dataset.mode));
            });

            document.querySelectorAll('.preset-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    setMode(btn.dataset.mode);
                    document.querySelectorAll('#termModeTabs [data-mode]').forEach((b) => {
                        if (b.dataset.mode === btn.dataset.mode) new bootstrap.Tab(b).show();
                    });
                    $('cmdInput').value = btn.dataset.command;
                    $('cmdInput').focus();
                });
            });

            async function runCommand() {
                const command = $('cmdInput').value.trim();
                if (!command) return;
                const timeout = parseInt($('cmdTimeout').value, 10) || 30;
                logLine('$ ' + command, 'head');
                $('cmdRunBtn').disabled = true;
                try {
                    const r = await api(endpoints.exec, { mode: state.mode, command: command, timeout: timeout });
                    if (r.stdout) logLine(r.stdout.replace(/\n$/, ''));
                    if (r.stderr) logLine(r.stderr.replace(/\n$/, ''), 'err');
                    logLine('[exit ' + r.exit_code + (r.timed_out ? ' · timed out' : '') + ' · ' + r.duration_ms + 'ms]', r.exit_code === 0 ? '' : 'err');
                } catch (e) {
                    logLine('! ' + e.message, 'err');
                } finally {
                    $('cmdRunBtn').disabled = false;
                    $('cmdInput').focus();
                }
            }

            $('cmdRunBtn').addEventListener('click', runCommand);
            $('cmdInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); runCommand(); } });
            $('cmdClearBtn').addEventListener('click', () => {
                out.textContent = 'Terminal ready. Output appears here.';
                out.dataset.fresh = '0';
            });

            function renderTree(list) {
                $('treePath').textContent = './' + (list.path === './' ? '' : list.path);
                const body = $('treeBody');
                body.textContent = '';

                if (list.parent !== null) {
                    const up = document.createElement('div');
                    up.className = 'file-entry';
                    up.innerHTML = '<i class="fas fa-level-up-alt"></i> ..';
                    up.addEventListener('click', () => loadTree(list.parent));
                    body.appendChild(up);
                }

                if (!list.entries.length) {
                    const empty = document.createElement('div');
                    empty.className = 'text-muted small px-1';
                    empty.textContent = 'Empty directory';
                    body.appendChild(empty);
                }

                list.entries.forEach((entry) => {
                    const row = document.createElement('div');
                    row.className = 'file-entry';
                    row.title = entry.path + (entry.size !== null ? ' · ' + entry.size + ' B' : '');
                    const icon = document.createElement('i');
                    icon.className = entry.type === 'dir'
                        ? 'fas fa-folder text-warning'
                        : 'fas fa-file-lines';
                    const label = document.createElement('span');
                    label.textContent = entry.name;
                    row.appendChild(icon);
                    row.appendChild(label);
                    row.addEventListener('click', () => {
                        if (entry.type === 'dir') {
                            loadTree(entry.path);
                        } else {
                            openFile(entry.path, row);
                        }
                    });
                    body.appendChild(row);
                });
            }

            async function loadTree(path) {
                try {
                    renderTree(await api(endpoints.tree, path ? { path: path } : {}));
                } catch (e) {
                    $('treeBody').textContent = e.message;
                }
            }

            async function openFile(path, row) {
                try {
                    const f = await api(endpoints.read, { path: path });
                    state.loadedPath = f.path;
                    state.loadedSha = f.sha256;
                    state.isNew = false;
                    state.confirmedPath = null;
                    $('editor').value = f.content;
                    $('pathInput').value = f.path;
                    $('fileMeta').textContent = f.path + ' · ' + f.size + ' B · ' + f.sha256.slice(0, 12);
                    setDirty(false);
                    setConfirmed(false);
                    document.querySelectorAll('.file-entry').forEach((el) => el.classList.remove('active'));
                    if (row) row.classList.add('active');
                } catch (e) {
                    alertOpen(e.message);
                }
            }

            function alertOpen(message) {
                $('fileMeta').textContent = 'error';
                window.alert(message);
            }

            function setDirty(dirty) {
                state.dirty = dirty;
                const badge = $('dirtyBadge');
                badge.textContent = dirty ? 'unsaved changes' : 'saved';
                badge.style.background = dirty ? '#d97706' : '#6b7280';
                refreshButtons();
            }

            function setConfirmed(confirmed) {
                const badge = $('confirmBadge');
                if (confirmed) {
                    badge.textContent = 'target confirmed: ' + state.confirmedPath;
                    badge.style.background = '#15803d';
                } else {
                    badge.textContent = 'target not confirmed';
                    badge.style.background = '#6b7280';
                }
                refreshButtons();
            }

            function refreshButtons() {
                $('confirmBtn').disabled = !state.loadedPath;
                $('saveBtn').disabled = !(state.loadedPath && state.confirmedPath === state.loadedPath);
            }

            $('openBtn').addEventListener('click', () => {
                const path = $('pathInput').value.trim();
                if (path) openFile(path);
            });
            $('pathInput').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); $('openBtn').click(); }
            });

            $('newFileBtn').addEventListener('click', () => {
                const path = $('pathInput').value.trim();
                if (!path) { window.alert('Enter the path for the new file first.'); return; }
                state.loadedPath = path;
                state.loadedSha = NEW_TOKEN;
                state.isNew = true;
                state.confirmedPath = null;
                $('editor').value = '';
                $('fileMeta').textContent = path + ' · new file';
                setDirty(true);
                setConfirmed(false);
                window.alert('New file staged: ' + path + '. Confirm the target file before saving.');
            });

            $('editor').addEventListener('input', () => {
                if (state.loadedPath) setDirty(true);
            });

            $('confirmBtn').addEventListener('click', () => {
                if (!state.loadedPath) return;
                state.confirmedPath = state.loadedPath;
                setConfirmed(true);
            });

            $('saveBtn').addEventListener('click', async () => {
                if (!state.loadedPath) return;
                if (state.confirmedPath !== state.loadedPath) {
                    window.alert('Confirm the target file first.');
                    return;
                }
                $('saveBtn').disabled = true;
                try {
                    const r = await api(endpoints.write, {
                        path: state.loadedPath,
                        content: $('editor').value,
                        confirm_path: state.confirmedPath,
                        expected_sha256: state.isNew ? NEW_TOKEN : state.loadedSha
                    });
                    state.loadedSha = r.sha256;
                    state.isNew = false;
                    $('fileMeta').textContent = r.path + ' · ' + r.bytes + ' B · ' + r.sha256.slice(0, 12);
                    setDirty(false);
                    setConfirmed(true);
                    window.toastr ? toastr.success('Saved ' + r.path) : null;
                } catch (e) {
                    window.alert(e.message);
                } finally {
                    refreshButtons();
                }
            });

            loadTree(null);
        })();
    </script>
@endsection
