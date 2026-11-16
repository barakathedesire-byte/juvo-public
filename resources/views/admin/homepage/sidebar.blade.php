@extends('admin.layouts.admin')

@section('title', 'Marketplace Sidebar')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Marketplace Sidebar</h4>
        <a href="{{ route('admin.homepage.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <p class="text-muted small mb-3">
                These sections appear at the top of the <strong>All Categories</strong> sidebar in the storefront header,
                above the category list. Order them top to bottom; a section with no links is hidden.
            </p>

            <form method="POST" action="{{ route('admin.homepage.sidebar.update') }}">
                @csrf

                <div id="jvSections"></div>

                <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="jvAddSection">
                    <i class="fas fa-plus me-1"></i>Add section
                </button>

                <hr class="my-4">
                <button class="btn btn-primary">Save sidebar</button>
            </form>
        </div>
    </div>

    <template id="jvSectionTpl">
        <div class="card shadow-sm mb-3 jv-sec">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div class="row g-2 flex-grow-1">
                        <div class="col-md-7">
                            <label class="form-label small mb-1">Section title</label>
                            <input type="text" class="form-control form-control-sm" maxlength="80"
                                   name="sections[__KEY__][title]" value="" placeholder="e.g. Groceries &amp; fresh">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Icon class (optional)</label>
                            <input type="text" class="form-control form-control-sm" maxlength="80"
                                   name="sections[__KEY__][icon]" value="" placeholder="fa-solid fa-cart-shopping">
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-move="-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-move="1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" class="btn btn-outline-danger" data-remove-section title="Remove section"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="jv-links"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-link><i class="fas fa-plus me-1"></i>Add link</button>
            </div>
        </div>
    </template>

    <template id="jvLinkTpl">
        <div class="row g-2 align-items-center mb-2 jv-link">
            <div class="col-6 col-md-4">
                <input type="text" class="form-control form-control-sm" maxlength="80"
                       name="sections[__SEC__][links][__KEY__][label]" value="" placeholder="Link label">
            </div>
            <div class="col-6 col-md-5">
                <input type="text" class="form-control form-control-sm" maxlength="500"
                       name="sections[__SEC__][links][__KEY__][url]" value="" placeholder="/categories/slug or https://…">
            </div>
            <div class="col-5 col-md-2">
                <input type="text" class="form-control form-control-sm" maxlength="24"
                       name="sections[__SEC__][links][__KEY__][badge]" value="" placeholder="Badge">
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-link title="Remove link"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </template>

    <script>
    (function () {
        var sectionsEl = document.getElementById('jvSections');
        var sectionTpl = document.getElementById('jvSectionTpl').innerHTML.trim();
        var linkTpl = document.getElementById('jvLinkTpl').innerHTML.trim();
        var seq = 0;

        function nextKey(prefix) {
            seq++;
            return prefix + '-' + seq + '-' + Date.now().toString(36);
        }

        function addLink(sectionEl, data) {
            data = data || {};
            var key = nextKey('l');
            var secKey = sectionEl.getAttribute('data-key');
            var html = linkTpl
                .replaceAll('__SEC__', secKey)
                .replaceAll('__KEY__', key);
            var wrap = document.createElement('div');
            wrap.innerHTML = html;
            var row = wrap.firstElementChild;
            row.querySelector('[name$="[label]"]').value = data.label || '';
            row.querySelector('[name$="[url]"]').value = data.url || '';
            row.querySelector('[name$="[badge]"]').value = data.badge || '';
            sectionEl.querySelector('.jv-links').appendChild(row);
        }

        function addSection(data) {
            data = data || {};
            var key = nextKey('s');
            var wrap = document.createElement('div');
            wrap.innerHTML = sectionTpl.replaceAll('__KEY__', key);
            var card = wrap.firstElementChild;
            card.setAttribute('data-key', key);
            card.querySelector('[name$="[title]"]').value = data.title || '';
            card.querySelector('[name$="[icon]"]').value = data.icon || '';
            sectionsEl.appendChild(card);

            (data.links || []).forEach(function (link) { addLink(card, link); });

            if (!data.links || !data.links.length) {
                addLink(card, {});
            }
        }

        sectionsEl.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) { return; }
            var card = btn.closest('.jv-sec');
            var row = btn.closest('.jv-link');

            if (btn.hasAttribute('data-add-link')) {
                addLink(card, {});
            } else if (btn.hasAttribute('data-remove-link')) {
                row.remove();
            } else if (btn.hasAttribute('data-remove-section')) {
                if (confirm('Remove this section?')) { card.remove(); }
            } else if (btn.hasAttribute('data-move')) {
                var dir = parseInt(btn.getAttribute('data-move'), 10);
                if (dir < 0 && card.previousElementSibling) {
                    sectionsEl.insertBefore(card, card.previousElementSibling);
                } else if (dir > 0 && card.nextElementSibling) {
                    sectionsEl.insertBefore(card.nextElementSibling, card);
                }
            }
        });

        document.getElementById('jvAddSection').addEventListener('click', function () {
            addSection({});
        });

        @foreach($sections as $section)
            addSection(@json($section));
        @endforeach
    })();
    </script>
@endsection
