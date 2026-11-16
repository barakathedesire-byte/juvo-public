@extends('admin.layouts.admin')

@section('title', 'Add Page')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-0">Add Page</h4>
            <p class="text-muted mb-0">Create a new page, then build it with the visual editor.</p>
        </div>
        <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary btn-sm">Back to pages</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger mt-3">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.pages.store') }}" class="mt-3">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Page title</label>
                            <input type="text" name="title" id="pageTitle" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" required placeholder="e.g. Summer Sale">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">URL slug</label>
                            <div class="input-group">
                                <span class="input-group-text">/page/</span>
                                <input type="text" name="slug" id="pageSlug" class="form-control @error('slug') is-invalid @enderror"
                                       value="{{ old('slug') }}" placeholder="auto-generated-from-title">
                            </div>
                            <div class="form-text">Leave blank to generate from the title. Must be unique.</div>
                            @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Page type</label>
                            <select name="page_type" class="form-select @error('page_type') is-invalid @enderror" required>
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}" {{ old('page_type', 'custom') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('page_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Template</label>
                            <select name="template" class="form-select" required>
                                @foreach($templates as $key => $label)
                                    <option value="{{ $key }}" {{ old('template', 'default') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Use “Default” for the section builder. “Rich content” fits a single article.</div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Parent page (optional)</label>
                            <select name="parent_id" class="form-select">
                                <option value="">— None —</option>
                                @foreach($pages as $p)
                                    <option value="{{ $p->id }}" {{ (int) old('parent_id') === $p->id ? 'selected' : '' }}>{{ $p->title() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white"><h6 class="mb-0">SEO &amp; social sharing</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">SEO title</label>
                            <input type="text" name="seo_title" class="form-control" value="{{ old('seo_title') }}" maxlength="255">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Meta description</label>
                            <textarea name="seo_description" class="form-control" rows="3" maxlength="500">{{ old('seo_description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0">Publishing</h6></div>
                    <div class="card-body">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="1" {{ old('status', '1') === '1' ? 'selected' : '' }}>Published</option>
                            <option value="0" {{ old('status') === '0' ? 'selected' : '' }}>Draft</option>
                        </select>
                        <div class="form-text">Draft pages are not visible on the storefront.</div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="bi bi-magic me-1"></i>Create &amp; build
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('js')
    <script>
        (function () {
            const title = document.getElementById('pageTitle');
            const slug = document.getElementById('pageSlug');
            let touched = false;
            slug.addEventListener('input', () => touched = true);
            title.addEventListener('input', () => {
                if (touched) return;
                slug.value = title.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            });
        })();
    </script>
@endsection
