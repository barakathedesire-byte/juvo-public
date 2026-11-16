@extends('admin.layouts.admin')

@section('title', 'Footer')

@section('content')
    @php
        $socialLinks = old('social_links', $footer['social_links']);
        $columns = old('columns', $footer['columns']);
        $newsletter = $footer['newsletter'];
    @endphp

    <div class="mt-4 d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div>
            <h4 class="mb-0">Footer</h4>
            <p class="text-muted small mb-0">Everything shown in the storefront footer is managed here.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="jvLoadDefaults">
                <i class="fas fa-rotate-left me-1"></i>Load storefront defaults
            </button>
            <a href="{{ route('admin.homepage.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.homepage.footer.update') }}" id="jvFooterForm">
        @csrf

        <div class="card shadow-sm mt-3">
            <div class="card-header">Brand column</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" maxlength="500" class="form-control"
                              placeholder="Tell shoppers who Juvo is and what you offer.">{{ old('description', $footer['description']) }}</textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold">Social links</div>
                        <div class="text-muted small">Icon classes use Font Awesome, e.g. <code>fab fa-facebook-f</code>. Leave the URL empty to hide a link.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="jvAddSocial">
                        <i class="fas fa-plus me-1"></i>Add social link
                    </button>
                </div>
                <div id="jvSocialLinks"></div>

                <hr class="my-4">

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="newsletter_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="jvNewsletterEnabled"
                           name="newsletter_enabled" value="1" {{ old('newsletter_enabled', $newsletter['enabled']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="jvNewsletterEnabled">Show the newsletter signup form</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Newsletter heading</label>
                        <input type="text" name="newsletter_label" maxlength="120" class="form-control"
                               value="{{ old('newsletter_label', $newsletter['label']) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email placeholder</label>
                        <input type="text" name="newsletter_placeholder" maxlength="80" class="form-control"
                               value="{{ old('newsletter_placeholder', $newsletter['placeholder']) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Button label</label>
                        <input type="text" name="newsletter_button" maxlength="40" class="form-control"
                               value="{{ old('newsletter_button', $newsletter['button']) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Footer columns</span>
                <span class="badge {{ $hasCustomColumns ? 'bg-success' : 'bg-secondary' }}">
                    {{ $hasCustomColumns ? 'Custom' : 'Storefront defaults' }}
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    These are the link columns shown after the brand column. The form starts with the current storefront
                    footer, including every link added by future footer updates. Move columns left or right, add links, or remove
                    anything you do not want to show.
                </p>

                <div id="jvColumns"></div>

                <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="jvAddColumn">
                    <i class="fas fa-plus me-1"></i>Add column
                </button>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header">Bottom bar</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Copyright text</label>
                        <input type="text" name="copyright" maxlength="160" class="form-control"
                               value="{{ old('copyright', $footer['copyright']) }}" placeholder="© {year} {site}. All rights reserved.">
                        <div class="form-text">Use <code>{year}</code> and <code>{site}</code> to fill them automatically.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bottom-right note</label>
                        <input type="text" name="note" maxlength="160" class="form-control"
                               value="{{ old('note', $footer['note']) }}" placeholder="Powered by Juvo Marketplace">
                        <div class="form-text">Shown next to the copyright line. Leave empty for the default.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 mb-5">
            <button class="btn btn-primary">Save footer</button>
            <a href="{{ route('admin.homepage.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <template id="jvSocialTpl">
        <div class="row g-2 align-items-center mb-2 jv-social">
            <div class="col-5 col-md-3">
                <input type="text" class="form-control form-control-sm" maxlength="80"
                       name="social_links[__KEY__][label]" value="" placeholder="Facebook">
            </div>
            <div class="col-7 col-md-3">
                <input type="text" class="form-control form-control-sm" maxlength="80"
                       name="social_links[__KEY__][icon]" value="" placeholder="fab fa-facebook-f">
            </div>
            <div class="col-10 col-md-5">
                <input type="text" class="form-control form-control-sm" maxlength="500"
                       name="social_links[__KEY__][url]" value="" placeholder="https://facebook.com/your-page">
            </div>
            <div class="col-2 col-md-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-social title="Remove social link"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </template>

    <template id="jvColumnTpl">
        <div class="card shadow-sm mb-3 jv-col">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div class="flex-grow-1" style="max-width:420px;">
                        <label class="form-label small mb-1">Column title</label>
                        <input type="text" class="form-control form-control-sm" maxlength="80"
                               name="columns[__KEY__][title]" value="" placeholder="e.g. Support">
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-move="-1" title="Move left"><i class="fas fa-arrow-left"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-move="1" title="Move right"><i class="fas fa-arrow-right"></i></button>
                        <button type="button" class="btn btn-outline-danger" data-remove-column title="Remove column"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="jv-links"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-link><i class="fas fa-plus me-1"></i>Add link</button>
            </div>
        </div>
    </template>

    <template id="jvLinkTpl">
        <div class="row g-2 align-items-center mb-2 jv-link">
            <div class="col-5 col-md-4">
                <input type="text" class="form-control form-control-sm" maxlength="80"
                       name="columns[__SEC__][links][__KEY__][label]" value="" placeholder="Link label">
            </div>
            <div class="col-6 col-md-7">
                <input type="text" class="form-control form-control-sm" maxlength="500"
                       name="columns[__SEC__][links][__KEY__][url]" value="" placeholder="/page/slug or https://…">
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-link title="Remove link"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </template>

    <script>
    (function () {
        var socialEl = document.getElementById('jvSocialLinks');
        var columnsEl = document.getElementById('jvColumns');
        var socialTpl = document.getElementById('jvSocialTpl').innerHTML.trim();
        var columnTpl = document.getElementById('jvColumnTpl').innerHTML.trim();
        var linkTpl = document.getElementById('jvLinkTpl').innerHTML.trim();
        var seq = 0;

        function nextKey(prefix) {
            seq++;
            return prefix + '-' + seq + '-' + Date.now().toString(36);
        }

        function addSocial(data) {
            data = data || {};
            var key = nextKey('s');
            var wrap = document.createElement('div');
            wrap.innerHTML = socialTpl.replaceAll('__KEY__', key);
            var row = wrap.firstElementChild;
            row.querySelector('[name$="[label]"]').value = data.label || '';
            row.querySelector('[name$="[icon]"]').value = data.icon || '';
            row.querySelector('[name$="[url]"]').value = data.url || '';
            socialEl.appendChild(row);
        }

        function addLink(columnEl, data) {
            data = data || {};
            var key = nextKey('l');
            var colKey = columnEl.getAttribute('data-key');
            var wrap = document.createElement('div');
            wrap.innerHTML = linkTpl
                .replaceAll('__SEC__', colKey)
                .replaceAll('__KEY__', key);
            var row = wrap.firstElementChild;
            row.querySelector('[name$="[label]"]').value = data.label || '';
            row.querySelector('[name$="[url]"]').value = data.url || '';
            columnEl.querySelector('.jv-links').appendChild(row);
        }

        function addColumn(data) {
            data = data || {};
            var key = nextKey('c');
            var wrap = document.createElement('div');
            wrap.innerHTML = columnTpl.replaceAll('__KEY__', key);
            var card = wrap.firstElementChild;
            card.setAttribute('data-key', key);
            card.querySelector('[name$="[title]"]').value = data.title || '';
            columnsEl.appendChild(card);

            (data.links || []).forEach(function (link) { addLink(card, link); });

            if (!data.links || !data.links.length) {
                addLink(card, {});
            }
        }

        socialEl.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-remove-social]');
            if (btn) {
                btn.closest('.jv-social').remove();
            }
        });

        columnsEl.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) { return; }
            var card = btn.closest('.jv-col');
            var row = btn.closest('.jv-link');

            if (btn.hasAttribute('data-add-link')) {
                addLink(card, {});
            } else if (btn.hasAttribute('data-remove-link')) {
                row.remove();
            } else if (btn.hasAttribute('data-remove-column')) {
                if (confirm('Remove this column?')) { card.remove(); }
            } else if (btn.hasAttribute('data-move')) {
                var dir = parseInt(btn.getAttribute('data-move'), 10);
                if (dir < 0 && card.previousElementSibling) {
                    columnsEl.insertBefore(card, card.previousElementSibling);
                } else if (dir > 0 && card.nextElementSibling) {
                    columnsEl.insertBefore(card.nextElementSibling, card);
                }
            }
        });

        document.getElementById('jvAddSocial').addEventListener('click', function () {
            addSocial({});
        });

        document.getElementById('jvAddColumn').addEventListener('click', function () {
            addColumn({});
        });

        document.getElementById('jvLoadDefaults').addEventListener('click', function () {
            if (!confirm('Replace the form with the current storefront defaults? Unsaved changes will be lost.')) {
                return;
            }

            var defaults = @json($defaults);
            var newsletter = defaults.newsletter || {};

            document.querySelector('[name="description"]').value = defaults.description || '';
            document.getElementById('jvNewsletterEnabled').checked = !!newsletter.enabled;
            document.querySelector('[name="newsletter_label"]').value = newsletter.label || '';
            document.querySelector('[name="newsletter_placeholder"]').value = newsletter.placeholder || '';
            document.querySelector('[name="newsletter_button"]').value = newsletter.button || '';
            document.querySelector('[name="copyright"]').value = defaults.copyright || '';
            document.querySelector('[name="note"]').value = defaults.note || '';

            socialEl.innerHTML = '';
            columnsEl.innerHTML = '';
            (defaults.social_links || []).forEach(addSocial);
            (defaults.columns || []).forEach(addColumn);
        });

        @foreach($socialLinks as $social)
            addSocial(@json($social));
        @endforeach

        @foreach($columns as $column)
            addColumn(@json($column));
        @endforeach
    })();
    </script>
@endsection
