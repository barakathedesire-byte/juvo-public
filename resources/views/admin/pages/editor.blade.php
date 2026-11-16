@extends('admin.layouts.admin')

@section('title', 'Page builder — '.$page->title())

@section('css')
    <link rel="stylesheet" href="{{ asset('css/page-builder.css') }}?v={{ @filemtime(public_path('css/page-builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/page-builder-admin.css') }}?v={{ @filemtime(public_path('css/page-builder-admin.css')) }}">
@endsection

@section('content')
    <div class="jvpb" id="jvpb">
        <div class="jvpb-topbar">
            <a href="{{ route('admin.pages.index') }}" class="jvpb-icon-btn" title="Back"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h5 class="jvpb-title">{{ $page->title() }}</h5>
                <div class="small text-muted">
                    <span class="jvpb-status-dot {{ $page->status ? 'jvpb-status-live' : 'jvpb-status-draft' }}"></span>
                    <span id="jvpb-status-label">{{ $page->status ? 'Published' : 'Draft' }}</span>
                    @if($isSystem) · <span class="badge bg-light text-dark border">System region: {{ $regionKey }}</span>@endif
                    @if(! $isSystem) · <code>/page/{{ $page->slug }}</code>@endif
                </div>
            </div>
            <span class="spacer"></span>
            <span class="badge bg-warning text-dark jvpb-unsaved" id="jvpb-unsaved">Unsaved changes</span>

            <div class="btn-group jvpb-device-tabs" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary active" data-device="desktop" title="Desktop"><i class="bi bi-display"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-device="tablet" title="Tablet"><i class="bi bi-tablet"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-device="mobile" title="Mobile"><i class="bi bi-phone"></i></button>
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary" id="jvpb-revisions"><i class="bi bi-clock-history me-1"></i>Revisions</button>
            <a href="{{ route('admin.pages.preview', $page->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>Preview</a>
            @if(! $isSystem && $page->template === 'content')
                <a href="{{ route('admin.pages.edit', $page->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-richtext me-1"></i>Rich content</a>
            @endif
            <button type="button" class="btn btn-sm btn-outline-dark" id="jvpb-save"><i class="bi bi-save me-1"></i>Save draft</button>
            @if($page->status)
                <button type="button" class="btn btn-sm btn-outline-warning" id="jvpb-unpublish">Unpublish</button>
            @else
                <button type="button" class="btn btn-sm btn-success" id="jvpb-publish"><i class="bi bi-send me-1"></i>Publish</button>
            @endif
        </div>

        @if($isProductPage)
            <div class="jvpb-panel mb-3">
                <div class="jvpb-panel-head">Product page template</div>
                <div class="jvpb-settings-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Layout template</label>
                            <select class="form-select form-select-sm" id="jvpb-page-template">
                                @foreach($templateOptions as $key => $label)
                                    <option value="{{ $key }}" {{ $page->template === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted small mb-0">Controls how individual product pages are rendered on the storefront.</p>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small">Components</span>
                        <span class="text-muted" style="font-size:12px;">Toggle which blocks appear on every product page.</span>
                    </div>
                    @php
                        $grouped = collect($appearanceFields)->groupBy('group');
                    @endphp
                    <div class="row g-2">
                        @foreach($grouped as $group => $fields)
                            <div class="col-md-6">
                                <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;margin:6px 0 4px;">{{ $group }}</div>
                                @foreach($fields as $field)
                                    <div class="form-check form-switch">
                                        <input class="form-check-input jvpb-appearance" type="checkbox"
                                               id="jvpb-app-{{ $field['key'] }}"
                                               data-key="{{ $field['key'] }}"
                                               {{ !empty($appearance[$field['key']]) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="jvpb-app-{{ $field['key'] }}">{{ $field['label'] }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="jvpb-layout">
            {{-- Component library --}}
            <div class="jvpb-panel">
                <div class="jvpb-panel-head">Components</div>
                <div class="jvpb-palette" id="jvpb-palette"></div>
            </div>

            {{-- Canvas --}}
            <div class="jvpb-panel">
                <div class="jvpb-panel-head d-flex justify-content-between align-items-center">
                    <span>Canvas</span>
                    <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400;" id="jvpb-block-count"></span>
                </div>
                <div class="jvpb-canvas jvpb-device-desktop" id="jvpb-canvas"></div>
            </div>

            {{-- Settings --}}
            <div class="jvpb-panel jvpb-settings">
                <div class="jvpb-panel-head">Settings</div>
                <div class="jvpb-settings-body" id="jvpb-settings">
                    <p class="text-muted small mb-0">Select a component on the canvas to edit its settings.</p>
                </div>
            </div>
        </div>

        {{-- Page settings (title/slug/SEO) --}}
        @if(! $isSystem)
            <div class="jvpb-panel mt-3 mb-4">
                <div class="jvpb-panel-head">Page settings &amp; SEO</div>
                <div class="jvpb-settings-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Page title</label>
                            <input type="text" class="form-control form-control-sm" id="jvpb-page-title" value="{{ $page->title() }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Slug</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">/page/</span>
                                <input type="text" class="form-control" id="jvpb-page-slug" value="{{ $page->slug }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">SEO title</label>
                            <input type="text" class="form-control form-control-sm" id="jvpb-seo-title" value="{{ $page->seo_title }}" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Social image (path or URL)</label>
                            <input type="text" class="form-control form-control-sm" id="jvpb-seo-image" value="{{ $page->seo_image }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Meta description</label>
                            <textarea class="form-control form-control-sm" id="jvpb-seo-description" rows="2" maxlength="500">{{ $page->seo_description }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Revisions modal --}}
    <div class="modal fade" id="jvpbRevisionsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Revision history</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="jvpb-revisions-body"><p class="text-muted small mb-0">Loading…</p></div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('vendor/toastr/js/toastr.min.js') }}"></script>
    <script>
        window.JV_PAGE = {
            pageId: {{ $page->id }},
            isSystem: {{ $isSystem ? 'true' : 'false' }},
            status: {{ $page->status ? 'true' : 'false' }},
            urls: {
                save: "{{ route('admin.pages.saveBlocks', $page->id) }}",
                publish: "{{ route('admin.pages.publish', $page->id) }}",
                unpublish: "{{ route('admin.pages.unpublish', $page->id) }}",
                revisions: "{{ route('admin.pages.revisions', $page->id) }}",
                restore: "{{ route('admin.pages.revisions.restore', [$page->id, '__REV__']) }}",
                media: "{{ route('admin.pages.media') }}"
            },
            csrf: "{{ csrf_token() }}",
            palette: @json($palette),
            context: @json($context),
            blocks: @json($blocks)
        };
    </script>
    <script src="{{ asset('js/page-builder.js') }}?v={{ @filemtime(public_path('js/page-builder.js')) }}"></script>
@endsection
