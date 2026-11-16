@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
        <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.categories.heading') }}</h6>
    </div>

    <div class="card-body">
        <form id="categoryForm" action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row">
                {{-- Language Tabs --}}
                <ul class="nav nav-tabs" id="languageTabs" role="tablist">
                    @foreach($activeLanguages as $language)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }}" 
                                    id="{{ $language->name }}-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#{{ $language->name }}" 
                                    type="button" 
                                    role="tab">
                                {{ ucwords($language->name) }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content mt-3" id="languageTabContent">
                    @foreach($activeLanguages as $language)
                        <div class="tab-pane fade show {{ $loop->first ? 'active' : '' }}" 
                             id="{{ $language->name }}" 
                             role="tabpanel">

                            {{-- Name --}}
                            <label class="form-label">{{ __('cms.categories.name') }} ({{ $language->code }})</label>
                            <input type="text" 
                                   name="translations[{{ $language->code }}][name]" 
                                   class="form-control @error('translations.'.$language->code.'.name') is-invalid @enderror" 
                                   value="{{ old('translations.' . $language->code . '.name') }}">
                            @error('translations.'.$language->code.'.name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            {{-- Description --}}
                            <label class="form-label mt-2">{{ __('cms.categories.description') }} ({{ $language->code }})</label>
                            <textarea id="description_{{ $language->code }}" 
                                      name="translations[{{ $language->code }}][description]" 
                                      class="form-control ck-editor-multi-languages @error('translations.'.$language->code.'.description') is-invalid @enderror">{{ old('translations.' . $language->code . '.description') }}</textarea>
                            @error('translations.'.$language->code.'.description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                           {{-- Icon --}}
                            <p class="text-muted small mt-2">Use a Font Awesome class (e.g. <code>fa-solid fa-mobile-screen</code>) for the category icon.</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Icon (Font Awesome)</label>
                    <input type="text" name="icon" class="form-control @error('icon') is-invalid @enderror" value="{{ old('icon') }}" placeholder="fa-solid fa-tag">
                    @error('icon')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('cms.categories.slug') ?? 'Slug' }}</label>
                    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug') }}" placeholder="auto-generated-from-name">
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Leave blank to generate from the English name.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ __('cms.categories.parent') ?? 'Parent category' }}</label>
                    <select name="parent_category_id" class="form-select @error('parent_category_id') is-invalid @enderror">
                        <option value="">— None (top level) —</option>
                        @foreach(($parentCategories ?? []) as $parent)
                            <option value="{{ $parent->id }}" {{ (string) old('parent_category_id') === (string) $parent->id ? 'selected' : '' }}>
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
                        <option value="1" {{ old('status', '1') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <button type="submit" class="mt-3 btn btn-primary">
                {{ __('cms.categories.button') }}
            </button>
        </form>
    </div>
</div>
@endsection

@section('js')
<script>
document.addEventListener("DOMContentLoaded", function () {
    @if ($errors->any())
        var firstErrorElement = document.querySelector('.is-invalid');
        if (firstErrorElement) {
            var tabPane = firstErrorElement.closest('.tab-pane');
            if (tabPane) {
                var tabId = tabPane.getAttribute('id');
                var triggerEl = document.querySelector(`button[data-bs-target="#${tabId}"]`);
                if (triggerEl) {
                    var tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                }
            }
        }
    @endif
});
</script>

<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
const LANG_CODES = {!! json_encode($activeLanguages->pluck('code')) !!};
const CKEDITORS = {};

document.querySelectorAll('.ck-editor-multi-languages').forEach((el) => {
    const id = el.id;
    ClassicEditor.create(el)
        .then(editor => {
            CKEDITORS[id] = editor;
        })
        .catch(error => {
            console.error('CKEditor init error', error);
        });
});

function previewImage(input, langCode) {
    var file = input.files[0];
    var previewElement = document.getElementById('image_preview_' + langCode);
    var previewImage = document.getElementById('image_preview_img_' + langCode);
    var hiddenInput = document.getElementById('image_base64_' + langCode);

    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            previewElement.style.display = 'block';
            previewImage.src = e.target.result;
            hiddenInput.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        previewElement.style.display = 'none';
        hiddenInput.value = '';
    }
}

function base64ToFile(dataurl, baseName) {
    if (!dataurl || dataurl.indexOf(',') === -1) throw new Error('Invalid base64 data');
    var arr = dataurl.split(',');
    var mimeMatch = arr[0].match(/data:(.*);base64/);
    if (!mimeMatch) throw new Error('Invalid mime in base64 data');
    var mime = mimeMatch[1];
    var ext = mime.split('/')[1].split('+')[0];
    if (ext === 'jpeg') ext = 'jpg';
    var bstr = atob(arr[1]);
    var n = bstr.length;
    var u8arr = new Uint8Array(n);
    for (var i = 0; i < n; i++) {
        u8arr[i] = bstr.charCodeAt(i);
    }
    var filename = baseName + '.' + ext;
    return new File([u8arr], filename, { type: mime });
}

document.getElementById('categoryForm').addEventListener('submit', function (e) {
    for (const code of LANG_CODES) {
        const textareaId = 'description_' + code;
        const editor = CKEDITORS[textareaId];
        if (editor) {
            const textarea = document.getElementById(textareaId);
            if (textarea) textarea.value = editor.getData();
        }
    }

    for (const code of LANG_CODES) {
        const fileInput = document.getElementById('image_file_' + code);
        const base64Input = document.getElementById('image_base64_' + code);

        if (fileInput && fileInput.files.length === 0 && base64Input && base64Input.value) {
            try {
                const f = base64ToFile(base64Input.value, 'image_' + code);
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(f);
                fileInput.files = dataTransfer.files;
            } catch (err) {
                console.error('base64 -> File conversion failed for', code, err);
            }
        }
    }
});
</script>
@endsection
