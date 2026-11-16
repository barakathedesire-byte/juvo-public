
@extends('admin.layouts.admin')

@section('content')
    <div class="card mt-4">
        <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
            <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.categories.heading') }}</h6>
            <button type="button" class="btn btn-sm btn-light" id="toggle-preview">Toggle Live Preview</button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6" id="edit-col">
                    <form action="{{ route('admin.categories.update', $category->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <!-- Display Validation Errors -->
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                
                        <div class="row">
                            <ul class="nav nav-tabs" id="languageTabs" role="tablist">
                                @foreach($activeLanguages as $language)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" 
                                                id="{{ $language->name }}-tab" 
                                                data-bs-toggle="tab" 
                                                data-bs-target="#{{ $language->name }}" 
                                                type="button" role="tab">
                                                {{  ucwords($language->name) }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                    
                            <div class="tab-content mt-3" id="languageTabContent">
                                @foreach($activeLanguages as $language)
                                    @php
                                        $translation = $category->translations->where('language_code', $language->code)->first();
                                    @endphp
                                    <div class="tab-pane fade show {{ $loop->first ? 'active' : '' }}" 
                                         id="{{ $language->name }}" 
                                         role="tabpanel">
                                
                                        <!-- Name Field -->
                                        <label class="form-label">{{ __('cms.categories.name') }} ({{ $language->code }})</label>
                                        <input type="text" 
                                               name="translations[{{ $language->code }}][name]" 
                                               data-lang="{{ $language->code }}"
                                               class="form-control cat-name @error('translations.{{ $language->code }}.name') is-invalid @enderror" 
                                               value="{{ $translation->name ?? '' }}"
                                               required>
                                        @error('translations.{{ $language->code }}.name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                
                                        <!-- Description Field -->
                                        <label class="form-label mt-2">{{ __('cms.categories.description') }} ({{ $language->code }})</label>
                                        <textarea name="translations[{{ $language->code }}][description]" 
                                                  data-lang="{{ $language->code }}"
                                                  class="form-control ck-editor-multi-languages cat-desc @error('translations.{{ $language->code }}.description') is-invalid @enderror">
                                            {{ $translation->description ?? '' }}
                                        </textarea>
                                        @error('translations.{{ $language->code }}.description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror

                                        <!-- Image Upload Field -->
                                        <label class="form-label mt-2">{{ __('cms.categories.image') }} ({{ $language->code }})</label>
                                        <div class="custom-file">
                                            <label class="btn btn-primary" for="image_file_{{ $language->code }}">{{ __('cms.categories.choose_file') }}</label>
                                            <input type="file" name="translations[{{ $language->code }}][image]" 
                                                accept="image/*" 
                                                class="form-control d-none @error('translations.{{ $language->code }}.image') is-invalid @enderror" 
                                                id="image_file_{{ $language->code }}" 
                                                onchange="previewImage(this, '{{ $language->code }}')">
                                        </div>

                                        <!-- Image Preview -->
                                        <div id="image_preview_{{ $language->code }}" class="mt-2" 
                                             style="{{ isset($translation->image_url) ? 'display: block;' : 'display: none;' }}">
                                            <img id="image_preview_img_{{ $language->code }}" 
                                                 data-lang="{{ $language->code }}"
                                                 src="{{ isset($translation->image_url) ? asset('storage/' . $translation->image_url) : '#' }}" 
                                                 alt="{{ __('cms.categories.image_preview') }}" class="img-thumbnail" style="max-width: 200px;">
                                        </div>

                                        @error('translations.{{ $language->code }}.image')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror

                                    </div> 
                                @endforeach
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Icon (Font Awesome)</label>
                                <input type="text" name="icon" class="form-control @error('icon') is-invalid @enderror" value="{{ old('icon', $category->icon) }}" placeholder="fa-solid fa-tag">
                                @error('icon')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('cms.categories.slug') ?? 'Slug' }}</label>
                                <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
                                       value="{{ old('slug', $category->slug) }}">
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ __('cms.categories.parent') ?? 'Parent category' }}</label>
                                <select name="parent_category_id" class="form-select @error('parent_category_id') is-invalid @enderror">
                                    <option value="">— None (top level) —</option>
                                    @foreach(($parentCategories ?? []) as $parent)
                                        <option value="{{ $parent->id }}" {{ (string) old('parent_category_id', $category->parent_category_id) === (string) $parent->id ? 'selected' : '' }}>
                                            {{ optional($parent->translation)->name ?? $parent->slug }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('parent_category_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ __('cms.categories.status') ?? 'Status' }}</label>
                                <select name="status" class="form-select @error('status') is-invalid @enderror">
                                    <option value="1" {{ (string) old('status', $category->status ? '1' : '0') === '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ (string) old('status', $category->status ? '1' : '0') === '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="mt-3 btn btn-primary">{{ cms_translate('categories.button') }}</button>
                        <a href="{{ route('category.show', $category->slug) }}" target="_blank" class="mt-3 btn btn-outline-secondary">View on Storefront</a>
                    </form>
                </div>
                <div class="col-md-6" id="preview-col">
                    <div class="border rounded p-3">
                        <h5 class="mb-3">Live Preview</h5>
                        <div class="jv-preview-frame">
                            <div class="jv-preview-header" style="background:#f7f7f7;padding:24px 20px;border-radius:8px;">
                                <nav class="jv-breadcrumb" id="pv-breadcrumb" style="font-size:14px;margin-bottom:10px;color:#666;">
                                    <a href="#">Home</a> <i class="fa fa-angle-right"></i>
                                    <span id="pv-name">{{ $category->translation->name ?? 'Category' }}</span>
                                </nav>
                                <h2 class="jv-page-title" id="pv-title" style="font-size:28px;font-weight:700;margin:0;">{{ $category->translation->name ?? 'Category' }}</h2>
                                <p id="pv-desc" style="color:#555;margin-top:10px;max-width:720px;">{!! $category->translation->description ?? '' !!}</p>
                            </div>
                            <div class="mt-3">
                                <img id="pv-img" src="{{ optional($category->translation)->image_url ? asset('storage/'.optional($category->translation)->image_url) : 'https://placehold.co/600x200?text=Category' }}" alt="" style="width:100%;max-height:240px;object-fit:cover;border-radius:8px;">
                            </div>
                            <div class="mt-3">
                                <div class="d-flex gap-2 flex-wrap" style="opacity:.6">
                                    @for($i=0;$i<6;$i++)
                                        <div style="width:calc(33.33%-8px);min-height:180px;background:#f1f1f1;border-radius:8px;"></div>
                                    @endfor
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 small text-muted">Preview reflects English (en) values by default; switch language tab to see form values.</div>
                        <div class="mt-2">
                            <iframe id="pv-iframe" src="{{ route('category.show', $category->slug) }}" style="width:100%;height:520px;border:1px solid #eee;border-radius:8px;display:none;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
    const catEditEditors = {};
    let previewIframe = document.getElementById('pv-iframe');
    let previewCol = document.getElementById('preview-col');
    let editCol = document.getElementById('edit-col');
    let toggleBtn = document.getElementById('toggle-preview');
    let previewMode = 'inline';
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(){
            previewMode = previewMode === 'inline' ? 'iframe' : 'inline';
            document.querySelector('.jv-preview-frame').style.display = previewMode === 'inline' ? 'block' : 'none';
            if (previewIframe) previewIframe.style.display = previewMode === 'iframe' ? 'block' : 'none';
            toggleBtn.textContent = previewMode === 'inline' ? 'Switch to Full Page Preview' : 'Switch to Inline Preview';
        });
    }

    function previewImage(input, langCode) {
        var file = input.files[0];
        var previewElement = document.getElementById('image_preview_' + langCode);
        var previewImage = document.getElementById('image_preview_img_' + langCode);

        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                previewElement.style.display = 'block';
                previewImage.src = e.target.result;
                let pvImg = document.getElementById('pv-img');
                if (pvImg) pvImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            previewElement.style.display = 'none';
        }
    }

    document.querySelectorAll('.ck-editor-multi-languages').forEach((element) => {
        ClassicEditor
            .create(element)
            .then(editor => {
                let lang = element.getAttribute('data-lang');
                catEditEditors[lang] = editor;
                editor.model.document.on('change:data', function () {
                    let data = editor.getData();
                    let pvDesc = document.getElementById('pv-desc');
                    if (pvDesc && element.getAttribute('data-lang') === 'en') {
                        pvDesc.innerHTML = data;
                    }
                });
            })
            .catch(error => {
                console.error(error);
            });
    });

    document.querySelectorAll('.cat-name').forEach(function(inp){
        inp.addEventListener('input', function(){
            if (inp.getAttribute('data-lang') === 'en') {
                document.getElementById('pv-name').textContent = inp.value;
                document.getElementById('pv-title').textContent = inp.value;
            }
        });
    });
</script>
@endsection

