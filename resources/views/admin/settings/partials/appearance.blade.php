<form method="POST" action="{{ route('admin.admin-settings.appearance.update') }}">
    @csrf
    @method('PUT')

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-2" style="background:var(--juvo-primary,#f68b1e)">
                    <strong class="text-white"><i class="fas fa-palette me-1"></i>Brand &amp; colours</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ([
                            'primary_color' => ['label' => 'Primary colour', 'help' => 'Buttons, links, active nav, headers'],
                            'accent_color' => ['label' => 'Accent (deep)', 'help' => 'Hover states, gradients'],
                            'sidebar_bg' => ['label' => 'Sidebar start', 'help' => 'Top of the sidebar gradient'],
                            'sidebar_bg_end' => ['label' => 'Sidebar end', 'help' => 'Bottom of the sidebar gradient'],
                            'sidebar_text' => ['label' => 'Sidebar text', 'help' => 'Navigation label colour'],
                        ] as $field => $meta)
                            <div class="col-md-6">
                                <label class="form-label small mb-1" for="field-{{ $field }}">{{ $meta['label'] }}</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color appearance-input"
                                           id="field-{{ $field }}" name="{{ $field }}"
                                           value="{{ old($field, $settings[$field]) }}" style="max-width:56px">
                                    <input type="text" class="form-control font-monospace appearance-input"
                                           value="{{ old($field, $settings[$field]) }}"
                                           oninput="this.previousElementSibling.value=this.value.startsWith('#')?this.value:'#'+this.value;this.previousElementSibling.dispatchEvent(new Event('input'))"
                                           onfocus="this.previousElementSibling.dispatchEvent(new Event('input'))">
                                </div>
                                <small class="text-muted">{{ $meta['help'] }}</small>
                                @error($field)<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        @endforeach

                        <div class="col-md-6">
                            <label class="form-label small mb-1" for="field-content_bg">Content background</label>
                            <input type="text" class="form-control form-control-sm appearance-input font-monospace"
                                   id="field-content_bg" name="content_bg"
                                   value="{{ old('content_bg', $settings['content_bg']) }}"
                                   placeholder="#eef1f8 — leave empty to keep the gradient">
                            <small class="text-muted">Optional flat background; empty keeps the default canvas.</small>
                            @error('content_bg')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small mb-1" for="field-border_radius">Corner radius</label>
                            <input type="text" class="form-control form-control-sm appearance-input font-monospace"
                                   id="field-border_radius" name="border_radius"
                                   value="{{ old('border_radius', $settings['border_radius']) }}" placeholder="22px">
                            <small class="text-muted">Rounds cards, panels and nav pills.</small>
                            @error('border_radius')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small mb-1" for="field-font_family">Font family</label>
                            <input type="text" class="form-control form-control-sm appearance-input"
                                   id="field-font_family" name="font_family"
                                   value="{{ old('font_family', $settings['font_family']) }}" placeholder="Nunito">
                            @error('font_family')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small mb-1" for="field-brand_name">Brand name</label>
                            <input type="text" class="form-control form-control-sm"
                                   id="field-brand_name" name="brand_name"
                                   value="{{ old('brand_name', $settings['brand_name']) }}" placeholder="Juvo Admin">
                            <small class="text-muted">Shown in the top bar.</small>
                            @error('brand_name')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="field-animations" name="animations" value="1"
                                       @checked(old('animations', $settings['animations']))>
                                <label class="form-check-label" for="field-animations">Enable animations &amp; transitions</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="field-compact_sidebar" name="compact_sidebar" value="1"
                                       @checked(old('compact_sidebar', $settings['compact_sidebar']))>
                                <label class="form-check-label" for="field-compact_sidebar">Compact sidebar (icons only)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-2" style="background:var(--juvo-primary,#f68b1e)">
                    <strong class="text-white"><i class="fas fa-eye me-1"></i>Live preview</strong>
                </div>
                <div class="card-body">
                    <div id="sidebarPreview" class="rounded p-3 mb-3"
                         style="background:linear-gradient(180deg,{{ $settings['sidebar_bg'] }} 0%,{{ $settings['sidebar_bg_end'] }} 100%)">
                        <div class="text-white-50 small mb-2">Sidebar</div>
                        <div class="px-2 py-1 rounded text-white small mb-1" style="background:{{ $settings['primary_color'] }}">
                            <i class="fas fa-home me-1"></i>Active item
                        </div>
                        <div class="px-2 py-1 small" style="color:{{ $settings['sidebar_text'] }}">
                            <i class="fas fa-box me-1"></i>Regular item
                        </div>
                    </div>

                    <span id="brandPreview" class="fw-bold" style="color:{{ $settings['primary_color'] }};font-size:18px">
                        {{ $settings['brand_name'] }}
                    </span>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm text-white" style="background:{{ $settings['primary_color'] }}">
                            Primary button
                        </button>
                        <a href="javascript:void(0)" class="ms-2" style="color:{{ $settings['primary_color'] }}">A text link</a>
                    </div>

                    <hr>
                    <p class="small text-muted mb-0">
                        Colours apply instantly in this preview. Save to persist them for every staff user — the
                        underlying design tokens (<code>--pb-accent</code>, <code>--pb-side-1</code>, …) are overridden
                        for the whole panel.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn text-white" style="background:var(--juvo-primary,#f68b1e)">
            <i class="fas fa-floppy-disk me-1"></i>Save appearance
        </button>
        <a href="{{ route('admin.admin-settings.index', ['tab' => 'appearance']) }}" class="btn btn-outline-secondary">Reset changes</a>
    </div>
</form>
