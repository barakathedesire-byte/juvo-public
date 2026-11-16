/* Juvo admin visual page builder */
(function () {
    var cfg = window.JV_PAGE;
    if (!cfg) return;

    var root = document.getElementById('jvpb');
    var elPalette = document.getElementById('jvpb-palette');
    var elCanvas = document.getElementById('jvpb-canvas');
    var elSettings = document.getElementById('jvpb-settings');
    var elCount = document.getElementById('jvpb-block-count');
    var elStatusLabel = document.getElementById('jvpb-status-label');

    var blocks = Array.isArray(cfg.blocks) ? cfg.blocks : [];
    var selected = -1;
    var dirty = false;

    /* ---------------- helpers ---------------- */

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function storageUrl(p) {
        if (!p) return '';
        return /^https?:\/\//i.test(p) ? p : '/storage/' + String(p).replace(/^\/+/, '');
    }

    function deepClone(o) { return JSON.parse(JSON.stringify(o)); }

    function uid() { return 'b' + Math.random().toString(36).slice(2, 9) + Date.now().toString(36).slice(-4); }

    function palette(type) { return cfg.palette[type] || null; }

    function defaultsFor(type) {
        var def = palette(type);
        return def ? deepClone(def.defaults || {}) : {};
    }

    function markDirty(v) {
        dirty = v !== false;
        root.classList.toggle('jvpb-dirty', dirty);
    }

    function toast(kind, msg) {
        if (window.toastr) toastr[kind](msg); else alert(msg);
    }

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(data || {})
        }).then(function (r) {
            return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; });
        });
    }

    /* ---------------- palette ---------------- */

    function renderPalette() {
        var groups = {};
        Object.keys(cfg.palette).forEach(function (type) {
            var def = cfg.palette[type];
            var g = def.group || 'Other';
            (groups[g] = groups[g] || []).push({ type: type, def: def });
        });

        elPalette.innerHTML = '';
        Object.keys(groups).forEach(function (g) {
            var label = document.createElement('div');
            label.className = 'jvpb-group-label';
            label.textContent = g;
            elPalette.appendChild(label);

            groups[g].forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'jvpb-comp';
                btn.innerHTML = '<i class="' + esc(item.def.icon || 'bi bi-square') + '"></i><span>' + esc(item.def.label) + '</span>';
                btn.addEventListener('click', function () { addBlock(item.type); });
                elPalette.appendChild(btn);
            });
        });
    }

    function addBlock(type) {
        blocks.push({ id: uid(), type: type, hidden: false, settings: defaultsFor(type) });
        selected = blocks.length - 1;
        markDirty();
        renderCanvas();
        renderSettings();
    }

    /* ---------------- canvas ---------------- */

    function renderCanvas() {
        elCount.textContent = blocks.length + (blocks.length === 1 ? ' section' : ' sections');
        elCanvas.innerHTML = '';

        if (!blocks.length) {
            var empty = document.createElement('div');
            empty.className = 'jvpb-empty';
            empty.innerHTML = '<i class="bi bi-plus-square fs-2 d-block mb-2"></i>Add a component from the library to start building this page.';
            elCanvas.appendChild(empty);
            return;
        }

        blocks.forEach(function (block, i) {
            elCanvas.appendChild(blockCard(block, i));
        });
    }

    function blockCard(block, i) {
        var def = palette(block.type) || { label: block.type, icon: 'bi bi-square' };
        var card = document.createElement('div');
        card.className = 'jvpb-block' + (i === selected ? ' is-selected' : '') + (block.hidden ? ' is-hidden' : '');
        card.draggable = true;
        card.dataset.index = i;

        var head = document.createElement('div');
        head.className = 'jvpb-block-head';
        head.innerHTML = '<i class="bi bi-grip-vertical text-muted"></i><i class="' + esc(def.icon) + '" style="color:var(--jvpb-orange)"></i>' +
            '<span class="jvpb-block-name">' + esc(def.label) + '</span>' +
            '<span class="jvpb-block-type">' + esc(block.type) + '</span>' +
            '<span class="spacer"></span>';
        head.appendChild(iconBtn('bi-arrow-up', 'Move up', function () { move(i, -1); }));
        head.appendChild(iconBtn('bi-arrow-down', 'Move down', function () { move(i, 1); }));
        head.appendChild(iconBtn(block.hidden ? 'bi-eye-slash' : 'bi-eye', 'Show / hide', function () { blocks[i].hidden = !blocks[i].hidden; markDirty(); renderCanvas(); }));
        head.appendChild(iconBtn('bi-files', 'Duplicate', function () { duplicate(i); }));
        head.appendChild(iconBtn('bi-trash', 'Delete', function () { remove(i); }, 'danger'));

        var body = document.createElement('div');
        body.className = 'jvpb-block-body';
        body.innerHTML = previewHtml(block);

        head.addEventListener('click', function (e) { if (e.target.closest('.jvpb-icon-btn')) return; select(i); });
        body.addEventListener('click', function () { select(i); });

        card.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', String(i)); card.style.opacity = '.4'; });
        card.addEventListener('dragend', function () { card.style.opacity = '1'; });
        card.addEventListener('dragover', function (e) { e.preventDefault(); });
        card.addEventListener('drop', function (e) {
            e.preventDefault();
            var from = parseInt(e.dataTransfer.getData('text/plain'), 10);
            if (isNaN(from) || from === i) return;
            var item = blocks.splice(from, 1)[0];
            blocks.splice(i, 0, item);
            selected = i;
            markDirty();
            renderCanvas();
            renderSettings();
        });

        card.appendChild(head);
        card.appendChild(body);
        return card;
    }

    function iconBtn(icon, title, handler, extra) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'jvpb-icon-btn' + (extra ? ' ' + extra : '');
        b.title = title;
        b.innerHTML = '<i class="bi ' + icon + '"></i>';
        b.addEventListener('click', function (e) { e.stopPropagation(); handler(); });
        return b;
    }

    function move(i, dir) {
        var j = i + dir;
        if (j < 0 || j >= blocks.length) return;
        var t = blocks[i]; blocks[i] = blocks[j]; blocks[j] = t;
        selected = j;
        markDirty();
        renderCanvas();
        renderSettings();
    }

    function duplicate(i) {
        var copy = deepClone(blocks[i]);
        copy.id = uid();
        blocks.splice(i + 1, 0, copy);
        selected = i + 1;
        markDirty();
        renderCanvas();
        renderSettings();
    }

    function remove(i) {
        blocks.splice(i, 1);
        selected = Math.min(selected, blocks.length - 1);
        markDirty();
        renderCanvas();
        renderSettings();
    }

    function select(i) {
        selected = i;
        renderCanvas();
        renderSettings();
    }

    /* ---------------- canvas previews ---------------- */

    function previewHtml(block) {
        var s = block.settings || {};
        var t = block.type;

        function img(p, h) { return p ? '<img src="' + esc(storageUrl(p)) + '" style="max-height:' + (h || 90) + 'px;border-radius:8px;" onerror="this.style.display=\'none\'">' : ''; }

        switch (t) {
            case 'hero': {
                var items = s.items || [];
                var first = items[0] || {};
                return '<div class="jvpb-block-preview"><strong>' + (items.length) + ' slide(s)</strong>' +
                    (first.title ? ' · ' + esc(first.title) : '') + (first.badge ? ' · ' + esc(first.badge) : '') +
                    '<div class="mt-2">' + (first.image ? img(first.image, 120) : '<em>No image yet</em>') + '</div></div>';
            }
            case 'promo_banner':
                return '<div class="jvpb-block-preview"><strong>' + esc(s.heading || '') + '</strong>' + (s.subtitle ? '<div>' + esc(s.subtitle) + '</div>' : '') + (s.image ? '<div class="mt-2">' + img(s.image, 120) + '</div>' : '') + '</div>';
            case 'image':
                return img(s.image, 140) || '<div class="jvpb-block-preview"><em>No image selected</em></div>';
            case 'gallery':
                return '<div class="jvpb-block-preview">' + (s.images || []).map(function (p) { return img(p, 60); }).join(' ') + ' <span class="ms-1">' + ((s.images || []).length) + ' image(s)</span></div>';
            case 'product_carousel':
            case 'product_grid':
                return '<div class="jvpb-block-preview"><strong>' + esc(s.title || 'Products') + '</strong> · source: ' + esc(s.source || '') + ' · up to ' + esc(s.limit || '') + ' items<div class="d-flex gap-2 mt-2">' + placeholderCards(4) + '</div></div>';
            case 'category_tiles':
            case 'category_grid':
                return '<div class="jvpb-block-preview"><strong>' + esc(s.title || 'Categories') + '</strong> · source: ' + esc(s.source || '') + '<div class="d-flex gap-2 mt-2">' + placeholderCards(5) + '</div></div>';
            case 'heading':
                return '<div class="jvpb-block-preview"><' + esc(s.level || 'h2') + ' style="margin:0;">' + esc(s.text || '') + '</' + esc(s.level || 'h2') + '></div>';
            case 'text':
                return '<div class="jvpb-block-preview">' + (s.heading ? '<strong>' + esc(s.heading) + '</strong>' : '') + '<div class="text-truncate">' + esc((s.body || '').replace(/<[^>]*>/g, ' ')) + '</div></div>';
            case 'cta':
                return '<div class="jvpb-block-preview p-3 rounded" style="background:' + esc(s.bg_color || '#f68b1e') + ';color:' + esc(s.text_color || '#fff') + '"><strong>' + esc(s.heading || '') + '</strong>' + (s.subtitle ? '<div>' + esc(s.subtitle) + '</div>' : '') + '</div>';
            case 'countdown':
                return '<div class="jvpb-block-preview"><strong>' + esc(s.heading || '') + '</strong> · ends ' + esc(s.ends_at || '—') + '</div>';
            case 'newsletter':
                return '<div class="jvpb-block-preview"><strong>' + esc(s.heading || '') + '</strong> · button: ' + esc(s.button_label || '') + '</div>';
            case 'vendor_recruit':
                return '<div class="jvpb-block-preview"><strong>' + esc(s.heading || '') + '</strong> · ' + esc(s.cta_label || '') + '</div>';
            case 'notice':
                return '<div class="jvpb-block-preview"><span class="badge bg-light text-dark border">' + esc(s.tone || 'info') + '</span> ' + esc(s.text || '') + '</div>';
            case 'video':
                return '<div class="jvpb-block-preview"><i class="bi bi-film"></i> ' + esc(s.url || 'No video URL') + '</div>';
            case 'section':
                return '<div class="jvpb-block-preview"><strong>' + esc(s.heading || 'Section') + '</strong>' +
                    '<div class="text-truncate">' + esc((s.body || '').replace(/<[^>]*>/g, ' ')) + '</div>' +
                    (s.image ? '<div class="mt-2">' + img(s.image, 80) + '</div>' : '') + '</div>';
            case 'shape':
                var radius = s.shape === 'circle' ? '50%' : (s.shape === 'rounded' ? '18px' : (s.shape === 'line' ? '999px' : '60% 40% 55% 45% / 45% 55% 45% 55%'));
                return '<div class="jvpb-block-preview text-center"><span style="display:inline-block;width:' + esc(s.width || 120) + 'px;height:' + esc(s.height || 120) + 'px;background:' + esc(s.color || '#f68b1e') + ';opacity:' + ((Number(s.opacity) || 0) / 100) + ';border-radius:' + radius + ';"></span></div>';
            case 'spacer':
                return '<div class="jvpb-block-preview text-center">↕ ' + esc(s.height || 0) + 'px</div>';
            case 'divider':
                return '<hr style="border-top-style:' + esc(s.style || 'solid') + '">';
            case 'html':
                return '<div class="jvpb-block-preview"><span class="text-muted">Custom HTML (' + esc((s.html || '').length) + ' chars)</span></div>';
            default:
                return '<div class="jvpb-block-preview text-muted">No preview</div>';
        }
    }

    function placeholderCards(n) {
        var out = '';
        for (var i = 0; i < n; i++) {
            out += '<span style="width:44px;height:44px;border-radius:8px;background:#f3f4f6;display:inline-block"></span>';
        }
        return out;
    }

    /* ---------------- settings ---------------- */

    function renderSettings() {
        elSettings.innerHTML = '';
        if (selected < 0 || !blocks[selected]) {
            elSettings.innerHTML = '<p class="text-muted small mb-0">Select a component on the canvas to edit its settings.</p>';
            return;
        }
        var block = blocks[selected];
        var def = palette(block.type);
        if (!def) return;

        if (block.hidden) {
            var note = document.createElement('div');
            note.className = 'alert alert-warning py-2 small';
            note.textContent = 'This section is hidden and will not appear on the site.';
            elSettings.appendChild(note);
        }

        (def.fields || []).forEach(function (f) {
            elSettings.appendChild(buildField(f, block.settings, f.key));
        });
    }

    function buildField(f, holder, key) {
        var wrap = document.createElement('div');
        wrap.className = 'jvpb-field';

        if (f.type !== 'toggle') {
            var label = document.createElement('label');
            label.textContent = f.label || f.key;
            wrap.appendChild(label);
        }

        var get = function () { return holder[key]; };
        var set = function (v) { holder[key] = v; markDirty(); renderCanvas(); };

        wrap.appendChild(controlFor(f, holder, key, get, set));
        return wrap;
    }

    function controlFor(f, holder, key, get, set) {
        var v = get();

        switch (f.type) {
            case 'textarea':
            case 'richtext':
            case 'html': {
                var ta = document.createElement('textarea');
                ta.className = 'form-control';
                ta.rows = f.type === 'textarea' ? 2 : 5;
                ta.value = v == null ? '' : v;
                if (f.type === 'html') ta.placeholder = '<div>…</div>';
                ta.addEventListener('input', function () { set(ta.value); });
                return ta;
            }
            case 'number': {
                var n = document.createElement('input');
                n.type = 'number'; n.className = 'form-control';
                if (f.min != null) n.min = f.min;
                if (f.max != null) n.max = f.max;
                n.value = v == null ? '' : v;
                n.addEventListener('input', function () { set(n.value === '' ? '' : Number(n.value)); });
                return n;
            }
            case 'toggle': {
                var l = document.createElement('label');
                l.className = 'form-check form-switch d-flex align-items-center gap-2 mb-0';
                l.innerHTML = '<input class="form-check-input" type="checkbox"' + (v ? ' checked' : '') + '><span class="small">' + esc(f.label) + '</span>';
                l.querySelector('input').addEventListener('change', function (e) { set(e.target.checked); });
                return l;
            }
            case 'select': {
                var sel = document.createElement('select');
                sel.className = 'form-select';
                Object.keys(f.options || {}).forEach(function (opt) {
                    var o = document.createElement('option');
                    o.value = opt; o.textContent = f.options[opt];
                    if (String(v) === String(opt)) o.selected = true;
                    sel.appendChild(o);
                });
                sel.addEventListener('change', function () { set(sel.value); });
                return sel;
            }
            case 'color': {
                var cwrap = document.createElement('div');
                cwrap.className = 'd-flex gap-2 align-items-center';
                var c = document.createElement('input');
                c.type = 'color'; c.className = 'form-control form-control-color';
                c.value = /^#[0-9a-fA-F]{6}$/.test(v || '') ? v : '#f68b1e';
                var t = document.createElement('input');
                t.type = 'text'; t.className = 'form-control form-control-sm'; t.placeholder = 'inherit';
                t.value = v || '';
                c.addEventListener('input', function () { t.value = c.value; set(c.value); });
                t.addEventListener('input', function () { set(t.value || null); });
                cwrap.appendChild(c); cwrap.appendChild(t);
                return cwrap;
            }
            case 'datetime': {
                var dt = document.createElement('input');
                dt.type = 'datetime-local'; dt.className = 'form-control';
                if (v) { dt.value = String(v).replace(' ', 'T').slice(0, 16); }
                dt.addEventListener('input', function () { set(dt.value ? dt.value.replace('T', ' ') : null); });
                return dt;
            }
            case 'category': {
                return selectFromContext(get, set, cfg.context.categories || [], 'id', 'name', 'Select category');
            }
            case 'collection': {
                return selectFromContext(get, set, cfg.context.collections || [], 'id', 'name', 'Select collection');
            }
            case 'image': {
                return imageControl(get, set);
            }
            case 'images': {
                return imagesControl(holder, key, get, set);
            }
            case 'repeater': {
                return repeaterControl(f, holder, key, get, set);
            }
            default: { /* text, url */
                var i = document.createElement('input');
                i.type = 'text'; i.className = 'form-control';
                i.value = v == null ? '' : v;
                if (f.type === 'url' && cfg.context) {
                    var listId = 'jvpb-links-' + String(f.key).replace(/[^a-z0-9]/gi, '');
                    i.setAttribute('list', listId);
                    var dl = document.createElement('datalist');
                    dl.id = listId;
                    (cfg.context.categories || []).forEach(function (item) {
                        if (item.url) { var o = document.createElement('option'); o.value = item.url; o.label = item.name + ' (category)'; dl.appendChild(o); }
                    });
                    (cfg.context.collections || []).forEach(function (item) {
                        if (item.url) { var o = document.createElement('option'); o.value = item.url; o.label = item.name + ' (collection)'; dl.appendChild(o); }
                    });
                    (cfg.context.products || []).forEach(function (item) {
                        if (item.url) { var o = document.createElement('option'); o.value = item.url; o.label = item.name + ' (product)'; dl.appendChild(o); }
                    });
                    (cfg.context.pages || []).forEach(function (item) {
                        if (item.url) { var o = document.createElement('option'); o.value = item.url; o.label = item.name + ' (page)'; dl.appendChild(o); }
                    });
                    var wrap = document.createElement('div');
                    wrap.appendChild(i);
                    wrap.appendChild(dl);
                    i.addEventListener('input', function () { set(i.value); });
                    return wrap;
                }
                i.addEventListener('input', function () { set(i.value); });
                return i;
            }
        }
    }

    function selectFromContext(get, set, list, valKey, labelKey, placeholder) {
        var sel = document.createElement('select');
        sel.className = 'form-select';
        var o0 = document.createElement('option');
        o0.value = ''; o0.textContent = '— ' + placeholder + ' —';
        sel.appendChild(o0);
        list.forEach(function (item) {
            var o = document.createElement('option');
            o.value = item[valKey]; o.textContent = item[labelKey];
            if (String(get()) === String(item[valKey])) o.selected = true;
            sel.appendChild(o);
        });
        sel.addEventListener('change', function () { set(sel.value || null); });
        return sel;
    }

    function imageControl(get, set) {
        var wrap = document.createElement('div');
        wrap.className = 'jvpb-img-drop';

        var preview = document.createElement('div');
        function refresh() {
            var val = get();
            preview.innerHTML = val ? '<img src="' + esc(storageUrl(val)) + '">' : '<span class="text-muted small">No image</span>';
        }
        refresh();

        var file = document.createElement('input');
        file.type = 'file'; file.accept = 'image/*'; file.className = 'form-control form-control-sm mt-1';
        file.addEventListener('change', function () {
            if (!file.files[0]) return;
            uploadImage(file.files[0]).then(function (path) {
                if (path) { set(path); refresh(); toast('success', 'Image uploaded.'); }
            });
        });

        var url = document.createElement('input');
        url.type = 'text'; url.className = 'form-control form-control-sm mt-1'; url.placeholder = 'or paste image URL / path';
        url.value = get() || '';
        url.addEventListener('change', function () { set(url.value || null); refresh(); });

        var clear = document.createElement('button');
        clear.type = 'button'; clear.className = 'btn btn-sm btn-outline-danger mt-1';
        clear.textContent = 'Remove';
        clear.addEventListener('click', function () { set(null); url.value = ''; refresh(); });

        wrap.appendChild(preview);
        wrap.appendChild(file);
        wrap.appendChild(url);
        wrap.appendChild(clear);
        return wrap;
    }

    function imagesControl(holder, key, get, set) {
        var wrap = document.createElement('div');
        var list = document.createElement('div');
        list.className = 'd-flex flex-wrap gap-2 mb-2';

        function refresh() {
            list.innerHTML = '';
            (holder[key] || []).forEach(function (p, idx) {
                var cell = document.createElement('div');
                cell.style.cssText = 'position:relative;';
                cell.innerHTML = '<img src="' + esc(storageUrl(p)) + '" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--jvpb-border)">';
                var rm = document.createElement('button');
                rm.type = 'button'; rm.className = 'btn btn-sm btn-danger';
                rm.style.cssText = 'position:absolute;top:-6px;right:-6px;border-radius:50%;width:20px;height:20px;padding:0;line-height:1;';
                rm.innerHTML = '×';
                rm.addEventListener('click', function () { holder[key].splice(idx, 1); markDirty(); renderCanvas(); refresh(); });
                cell.appendChild(rm);
                list.appendChild(cell);
            });
        }
        refresh();

        var add = document.createElement('input');
        add.type = 'file'; add.accept = 'image/*'; add.className = 'form-control form-control-sm';
        add.addEventListener('change', function () {
            if (!add.files[0]) return;
            uploadImage(add.files[0]).then(function (path) {
                if (path) { holder[key] = holder[key] || []; holder[key].push(path); markDirty(); renderCanvas(); refresh(); add.value = ''; }
            });
        });

        var urlAdd = document.createElement('div');
        urlAdd.className = 'input-group input-group-sm mt-1';
        urlAdd.innerHTML = '<input type="text" class="form-control" placeholder="Image URL"><button type="button" class="btn btn-outline-secondary">Add</button>';
        urlAdd.querySelector('button').addEventListener('click', function () {
            var val = urlAdd.querySelector('input').value.trim();
            if (val) { holder[key] = holder[key] || []; holder[key].push(val); markDirty(); renderCanvas(); refresh(); urlAdd.querySelector('input').value = ''; }
        });

        wrap.appendChild(list);
        wrap.appendChild(add);
        wrap.appendChild(urlAdd);
        return wrap;
    }

    function repeaterControl(f, holder, key, get, set) {
        var wrap = document.createElement('div');
        var rowsWrap = document.createElement('div');

        function refresh() {
            rowsWrap.innerHTML = '';
            (holder[key] || []).forEach(function (row, idx) {
                rowsWrap.appendChild(repeaterRow(f, holder, key, row, idx, refresh));
            });
            if (!(holder[key] || []).length) {
                rowsWrap.innerHTML = '<p class="text-muted small">No items yet.</p>';
            }
        }

        var add = document.createElement('button');
        add.type = 'button'; add.className = 'btn btn-sm btn-outline-primary';
        add.innerHTML = '<i class="bi bi-plus"></i> Add ' + esc(f.item_label || 'item');
        add.addEventListener('click', function () {
            holder[key] = holder[key] || [];
            var row = {};
            (f.fields || []).forEach(function (sub) { row[sub.key] = sub.default != null ? sub.default : ''; });
            holder[key].push(row);
            markDirty();
            renderCanvas();
            refresh();
        });

        wrap.appendChild(rowsWrap);
        wrap.appendChild(add);
        refresh();
        return wrap;
    }

    function repeaterRow(f, holder, key, row, idx, refresh) {
        var box = document.createElement('div');
        box.className = 'jvpb-repeater-row';

        var head = document.createElement('div');
        head.className = 'jvpb-repeater-head';
        head.innerHTML = '<strong class="small">' + esc((f.item_label || 'Item') + ' ' + (idx + 1)) + '</strong><span class="spacer" style="flex:1"></span>';
        head.appendChild(iconBtn('bi-arrow-up', 'Up', function () {
            if (idx > 0) { var a = holder[key][idx - 1]; holder[key][idx - 1] = holder[key][idx]; holder[key][idx] = a; markDirty(); renderCanvas(); refresh(); }
        }));
        head.appendChild(iconBtn('bi-arrow-down', 'Down', function () {
            if (idx < holder[key].length - 1) { var a = holder[key][idx + 1]; holder[key][idx + 1] = holder[key][idx]; holder[key][idx] = a; markDirty(); renderCanvas(); refresh(); }
        }));
        head.appendChild(iconBtn('bi-files', 'Duplicate', function () {
            var copy = JSON.parse(JSON.stringify(holder[key][idx]));
            holder[key].splice(idx + 1, 0, copy);
            markDirty(); renderCanvas(); refresh();
        }));
        head.appendChild(iconBtn('bi-trash', 'Remove', function () { holder[key].splice(idx, 1); markDirty(); renderCanvas(); refresh(); }, 'danger'));
        box.appendChild(head);

        (f.fields || []).forEach(function (sub) {
            box.appendChild(buildField(sub, row, sub.key));
        });

        return box;
    }

    /* ---------------- media upload ---------------- */

    function uploadImage(file) {
        var fd = new FormData();
        fd.append('image', file);
        fd.append('_token', cfg.csrf);
        return fetch(cfg.urls.media, { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' }, body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) { return j.success ? j.path : null; })
            .catch(function () { toast('error', 'Upload failed.'); return null; });
    }

    /* ---------------- save / publish ---------------- */

    function pageSettings() {
        var t = document.getElementById('jvpb-page-title');
        var tpl = document.getElementById('jvpb-page-template');
        var appearance = {};
        document.querySelectorAll('.jvpb-appearance').forEach(function (el) {
            appearance[el.getAttribute('data-key')] = el.checked;
        });
        return {
            blocks: blocks,
            title: t ? t.value : null,
            slug: (document.getElementById('jvpb-page-slug') || {}).value || null,
            template: tpl ? tpl.value : null,
            seo_title: (document.getElementById('jvpb-seo-title') || {}).value || null,
            seo_description: (document.getElementById('jvpb-seo-description') || {}).value || null,
            seo_image: (document.getElementById('jvpb-seo-image') || {}).value || null,
            appearance: appearance
        };
    }

    var saving = false;
    function save(silent) {
        if (saving) return Promise.resolve();
        saving = true;
        return post(cfg.urls.save, pageSettings()).then(function (r) {
            saving = false;
            if (r.ok && r.body.success) {
                blocks = r.body.blocks || blocks;
                markDirty(false);
                if (!silent) toast('success', r.body.message || 'Draft saved.');
                renderCanvas();
            } else {
                toast('error', (r.body && r.body.message) || 'Save failed.');
            }
        }).catch(function () { saving = false; toast('error', 'Save failed.'); });
    }

    function publishAction(url, label) {
        save(true).then(function () {
            return post(url, {});
        }).then(function (r) {
            if (r.ok && r.body.success) {
                toast('success', r.body.message || label);
                elStatusLabel.textContent = url === cfg.urls.publish ? 'Published' : 'Draft';
                setTimeout(function () { window.location.reload(); }, 800);
            } else {
                toast('error', (r.body && r.body.message) || 'Action failed.');
            }
        });
    }

    /* ---------------- revisions ---------------- */

    function openRevisions() {
        var body = document.getElementById('jvpb-revisions-body');
        body.innerHTML = '<p class="text-muted small">Loading…</p>';
        var modal = new bootstrap.Modal(document.getElementById('jvpbRevisionsModal'));
        modal.show();
        fetch(cfg.urls.revisions, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.data || !j.data.length) { body.innerHTML = '<p class="text-muted small mb-0">No revisions yet.</p>'; return; }
                var html = '<table class="table table-sm align-middle"><thead><tr><th>When</th><th>Note</th><th>Status</th><th>Sections</th><th></th></tr></thead><tbody>';
                j.data.forEach(function (rev) {
                    html += '<tr><td class="small">' + esc(rev.created_at) + '</td><td class="small">' + esc(rev.note) + (rev.author ? '<div class="text-muted">' + esc(rev.author) + '</div>' : '') + '</td>' +
                        '<td><span class="badge bg-' + (rev.status === 'published' ? 'success' : 'secondary') + '">' + esc(rev.status) + '</span></td>' +
                        '<td class="small">' + esc(rev.blocks) + '</td>' +
                        '<td class="text-end"><button class="btn btn-sm btn-outline-primary" data-restore="' + rev.id + '">Restore</button></td></tr>';
                });
                html += '</tbody></table>';
                body.innerHTML = html;
                body.querySelectorAll('[data-restore]').forEach(function (b) {
                    b.addEventListener('click', function () { restore(b.dataset.restore, modal); });
                });
            });
    }

    function restore(revId, modal) {
        var url = cfg.urls.restore.replace('__REV__', revId);
        post(url, {}).then(function (r) {
            if (r.ok && r.body.success) {
                blocks = r.body.blocks || [];
                markDirty(false);
                renderCanvas();
                renderSettings();
                modal.hide();
                toast('success', r.body.message || 'Revision restored.');
            } else {
                toast('error', 'Restore failed.');
            }
        });
    }

    /* ---------------- device toggle ---------------- */

    document.querySelectorAll('[data-device]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-device]').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            elCanvas.className = 'jvpb-canvas jvpb-device-' + btn.dataset.device;
        });
    });

    /* ---------------- wire toolbar ---------------- */

    document.getElementById('jvpb-save').addEventListener('click', function () { save(false); });
    var pub = document.getElementById('jvpb-publish');
    if (pub) pub.addEventListener('click', function () { publishAction(cfg.urls.publish, 'Published.'); });
    var un = document.getElementById('jvpb-unpublish');
    if (un) un.addEventListener('click', function () { publishAction(cfg.urls.unpublish, 'Unpublished.'); });
    document.getElementById('jvpb-revisions').addEventListener('click', openRevisions);

    ['jvpb-page-title', 'jvpb-page-slug', 'jvpb-page-template', 'jvpb-seo-title', 'jvpb-seo-description', 'jvpb-seo-image'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', function () { markDirty(); });
    });

    document.querySelectorAll('.jvpb-appearance').forEach(function (el) {
        el.addEventListener('change', function () { markDirty(); });
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save(false); }
    });

    /* ---------------- initial render ---------------- */

    renderPalette();
    renderCanvas();
    renderSettings();
})();
