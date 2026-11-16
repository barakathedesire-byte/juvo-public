@extends('admin.layouts.admin')
@section('title', 'Storefront Builder — '.$template->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0"><i class="fas fa-layer-group me-2" style="color:#f68b1e;"></i>{{ $template->name }}</h4>
            <small class="text-muted">
                {{ ucfirst($template->scope) }} template
                @if($template->vendor) · {{ $template->vendor->name }} @endif
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.storefront.preview', $template->id) }}" id="previewLink" data-base="{{ route('admin.storefront.preview', $template->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-external-link-alt me-1"></i> Preview
            </a>
            <a href="{{ route('admin.storefront.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <i class="fas fa-circle-info me-1"></i>
            Add sections, edit their settings, then publish. A vendor's own saved store layout always takes precedence.
            @if($template->status)
                <span class="badge text-bg-success ms-1">Published {{ optional($template->published_at)->format('d M Y H:i') }}</span>
            @else
                <span class="badge text-bg-warning ms-1">Draft — not applied to stores</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="saveDraftBtn"><i class="fas fa-save me-1"></i> Save draft</button>
            <button type="button" class="btn btn-sm btn-primary" id="publishBtn"><i class="fas fa-rocket me-1"></i> Publish</button>
            @if($template->status)
                <button type="button" class="btn btn-sm btn-outline-danger" id="unpublishBtn"><i class="fas fa-eye-slash me-1"></i> Unpublish</button>
            @endif
        </div>
    </div>

    <div class="alert d-none" id="builderMsg"></div>

    <ul class="nav nav-tabs jv-builder-tabs mb-3" id="regionTabs">
        @foreach($regions as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $loop->first ? 'active' : '' }}" href="#" data-region="{{ $key }}">
                    {{ $label }}
                    <span class="badge text-bg-light border ms-1" data-count="{{ $key }}">{{ count($regionBlocks[$key] ?? []) }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="alert alert-light border d-flex justify-content-between align-items-center py-2 px-3">
                <small class="text-muted mb-0">Editing tab: <strong id="activeRegionLabel">{{ reset($regions) }}</strong></small>
                <small class="text-muted mb-0 d-none d-md-block">A vendor's own saved layout for this tab takes precedence.</small>
            </div>
            <div id="blockList"></div>
            <div id="emptyState" class="text-center text-muted border rounded p-5 d-none">
                <i class="fas fa-shapes fa-2x mb-2"></i>
                <p class="mb-0">No sections yet. Add one from the panel on the right.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white"><i class="fas fa-plus me-1"></i> Add a section</h6>
                </div>
                <div class="card-body d-grid gap-2" id="addButtons">
                    @foreach($blockTypes as $type => $def)
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start" data-add="{{ $type }}">
                            <i class="fas {{ $def['icon'] ?? 'fa-square' }} me-2"></i>{{ $def['label'] ?? $type }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="builderDefs">@json($blockTypes)</script>
    <script type="application/json" id="builderRegions">@json($regionBlocks)</script>
    <script type="application/json" id="builderRegionLabels">@json($regions)</script>
</div>
@endsection

@section('js')
<script>
    (function () {
        const defs = JSON.parse(document.getElementById('builderDefs').textContent);
        const regions = JSON.parse(document.getElementById('builderRegions').textContent);
        const regionLabels = JSON.parse(document.getElementById('builderRegionLabels').textContent);
        const list = document.getElementById('blockList');
        const empty = document.getElementById('emptyState');
        const msg = document.getElementById('builderMsg');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function normalize(blocks) {
            return (blocks || []).map(b => ({ id: b.id, type: b.type, hidden: !!b.hidden, settings: b.settings || {} }));
        }

        const state = {};
        Object.keys(regions).forEach(k => { state[k] = normalize(regions[k]); });
        let activeRegion = Object.keys(regions)[0] || 'home';

        const current = () => state[activeRegion];

        function showMsg(text, type) {
            msg.className = 'alert alert-' + type;
            msg.textContent = text;
            msg.classList.remove('d-none');
            clearTimeout(showMsg._t);
            showMsg._t = setTimeout(() => msg.classList.add('d-none'), 4000);
        }

        function uuid() {
            return (crypto.randomUUID ? crypto.randomUUID() : 'b' + Date.now() + Math.random().toString(16).slice(2));
        }

        function fieldControl(block, field) {
            const key = field.key;
            const val = block.settings[key];
            const wrap = document.createElement('div');
            wrap.className = 'mb-2';

            if (field.type === 'toggle') {
                wrap.className = 'form-check form-switch mb-2';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.checked = !!val;
                const id = 'f_' + block.id + '_' + key;
                input.id = id;
                input.addEventListener('change', () => block.settings[key] = input.checked);
                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.htmlFor = id;
                label.textContent = field.label || key;
                wrap.append(input, label);
                return wrap;
            }

            const label = document.createElement('label');
            label.className = 'form-label small mb-1';
            label.textContent = field.label || key;
            wrap.appendChild(label);

            let input;
            if (field.type === 'select') {
                input = document.createElement('select');
                input.className = 'form-select form-select-sm';
                Object.entries(field.options || {}).forEach(([v, l]) => {
                    const o = document.createElement('option');
                    o.value = v;
                    o.textContent = l;
                    if (String(val) === String(v)) o.selected = true;
                    input.appendChild(o);
                });
                input.addEventListener('change', () => block.settings[key] = input.value);
            } else if (field.type === 'repeater' || field.type === 'images') {
                input = document.createElement('textarea');
                input.className = 'form-control form-control-sm font-monospace';
                input.rows = 3;
                input.value = JSON.stringify(val ?? []);
                input.addEventListener('input', () => {
                    try { block.settings[key] = JSON.parse(input.value); input.classList.remove('is-invalid'); }
                    catch (e) { input.classList.add('is-invalid'); }
                });
            } else {
                const multi = ['textarea', 'richtext', 'html'].includes(field.type);
                input = document.createElement(multi ? 'textarea' : 'input');
                input.className = 'form-control form-control-sm';
                if (multi) input.rows = 3;
                else input.type = field.type === 'number' ? 'number' : (field.type === 'color' ? 'color' : 'text');
                if (field.type === 'number' && field.min !== undefined) input.min = field.min;
                if (field.type === 'number' && field.max !== undefined) input.max = field.max;
                input.value = val ?? '';
                input.addEventListener('input', () => {
                    block.settings[key] = field.type === 'number' ? (parseFloat(input.value) || 0) : input.value;
                });
            }
            wrap.appendChild(input);
            return wrap;
        }

        function renderBlock(block, index) {
            const def = defs[block.type] || { label: block.type, fields: [] };
            const card = document.createElement('div');
            card.className = 'card shadow-sm mb-3';

            const head = document.createElement('div');
            head.className = 'card-header d-flex justify-content-between align-items-center';

            const title = document.createElement('span');
            title.className = 'fw-semibold';
            title.innerHTML = '<i class="fas fa-grip-vertical text-muted me-2"></i>' + (def.label || block.type);

            const controls = document.createElement('div');
            controls.className = 'btn-group btn-group-sm';
            controls.innerHTML =
                '<button type="button" class="btn btn-outline-secondary" data-act="up"' + (index === 0 ? ' disabled' : '') + '><i class="fas fa-arrow-up"></i></button>' +
                '<button type="button" class="btn btn-outline-secondary" data-act="down"' + (index === current().length - 1 ? ' disabled' : '') + '><i class="fas fa-arrow-down"></i></button>' +
                '<button type="button" class="btn btn-outline-secondary" data-act="toggle"><i class="fas ' + (block.hidden ? 'fa-eye-slash' : 'fa-eye') + '"></i></button>' +
                '<button type="button" class="btn btn-outline-danger" data-act="remove"><i class="fas fa-trash"></i></button>';

            head.append(title, controls);
            card.appendChild(head);

            const body = document.createElement('div');
            body.className = 'card-body' + (block.hidden ? ' opacity-50' : '');
            (def.fields || []).forEach(f => body.appendChild(fieldControl(block, f)));
            card.appendChild(body);

            controls.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-act]');
                if (!btn) return;
                const act = btn.dataset.act;
                if (act === 'up' && index > 0) swap(index, index - 1);
                if (act === 'down' && index < current().length - 1) swap(index, index + 1);
                if (act === 'remove') { current().splice(index, 1); renderAll(); }
                if (act === 'toggle') { block.hidden = !block.hidden; renderAll(); }
            });

            return card;
        }

        function swap(a, b) {
            const c = current();
            [c[a], c[b]] = [c[b], c[a]];
            renderAll();
        }

        function renderAll() {
            const c = current();
            list.innerHTML = '';
            c.forEach((b, i) => list.appendChild(renderBlock(b, i)));
            empty.classList.toggle('d-none', c.length > 0);

            const label = document.getElementById('activeRegionLabel');
            if (label) label.textContent = regionLabels[activeRegion] || activeRegion;

            const pl = document.getElementById('previewLink');
            if (pl) pl.href = pl.dataset.base + '?region=' + encodeURIComponent(activeRegion);

            document.querySelectorAll('#regionTabs [data-count]').forEach(el => {
                const k = el.dataset.count;
                el.textContent = (state[k] || []).length;
            });
        }

        function addBlock(type) {
            const def = defs[type];
            if (!def) return;
            current().push({ id: uuid(), type: type, hidden: false, settings: JSON.parse(JSON.stringify(def.defaults || {})) });
            renderAll();
        }

        document.getElementById('addButtons').addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-add]');
            if (btn) addBlock(btn.dataset.add);
        });

        document.getElementById('regionTabs').addEventListener('click', (e) => {
            const tab = e.target.closest('[data-region]');
            if (!tab) return;
            e.preventDefault();
            activeRegion = tab.dataset.region;
            document.querySelectorAll('#regionTabs .nav-link').forEach(a => a.classList.toggle('active', a === tab));
            renderAll();
        });

        function send(url, method) {
            return fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ regions: JSON.stringify(state) }),
            }).then(async r => {
                const body = await r.json().catch(() => ({}));
                if (!r.ok) {
                    throw new Error(body.message || ('Request failed with status ' + r.status));
                }
                return body;
            });
        }

        document.getElementById('saveDraftBtn').addEventListener('click', () => {
            send(@json(route('admin.storefront.save', $template->id)), 'POST')
                .then(() => showMsg('Draft saved.', 'success'))
                .catch(e => showMsg(e.message || 'Could not save draft.', 'danger'));
        });

        document.getElementById('publishBtn').addEventListener('click', () => {
            send(@json(route('admin.storefront.publish', $template->id)), 'POST')
                .then(() => showMsg('Published.', 'success'))
                .catch(e => showMsg(e.message || 'Could not publish.', 'danger'));
        });

        const unpublishBtn = document.getElementById('unpublishBtn');
        if (unpublishBtn) {
            unpublishBtn.addEventListener('click', () => {
                fetch(@json(route('admin.storefront.unpublish', $template->id)), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                }).then(async r => {
                    if (!r.ok) throw new Error('Could not unpublish.');
                    return r.json();
                }).then(() => location.reload())
                  .catch(e => showMsg(e.message || 'Could not unpublish.', 'danger'));
            });
        }

        renderAll();
    })();
</script>
@endsection
