
@extends('admin.layouts.admin')

@section('content')
    <div class="card mt-4">
        <div class="card-header card-header-bg text-white">
            <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.banners.edit_banner') }}</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.banners.update', $banner->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-group">
                    <label for="type">{{ __('cms.banners.banner_type') }}</label>
                    <select name="type" class="form-control" required>
                        <option value="promotion" {{ $banner->type == 'promotion' ? 'selected' : '' }}>{{ __('cms.banners.promotion') }}</option>
                        <option value="sale" {{ $banner->type == 'sale' ? 'selected' : '' }}>{{ __('cms.banners.sale') }}</option>
                        <option value="seasonal" {{ $banner->type == 'seasonal' ? 'selected' : '' }}>{{ __('cms.banners.seasonal') }}</option>
                        <option value="featured" {{ $banner->type == 'featured' ? 'selected' : '' }}>{{ __('cms.banners.featured') }}</option>
                        <option value="announcement" {{ $banner->type == 'announcement' ? 'selected' : '' }}>{{ __('cms.banners.announcement') }}</option>
                    </select>
                </div>

                <div id="languages-container" class="mt-4">
                    @if(!empty($languages) && count($languages) > 0)
                        <ul class="nav nav-tabs" id="languageTabs" role="tablist">
                            @foreach($languages as $index => $language)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" 
                                            id="{{ $language->name }}-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#{{ $language->name }}" 
                                            type="button" role="tab">
                                        {{ ucwords($language->name) }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content mt-3" id="languageTabContent">
                            @foreach($languages as $index => $language)
                                @php
                                    $bannerTranslation = $translations[$language->code] ?? new stdClass();
                                @endphp
                                <div class="tab-pane fade show {{ $loop->first ? 'active' : '' }}" 
                                     id="{{ $language->name }}" role="tabpanel">
                                    <div class="form-group">
                                        <label for="languages[{{ $index }}][title]">{{ __('cms.banners.title') }}</label>
                                        <input type="text" name="languages[{{ $index }}][title]" 
                                               class="form-control @error('languages.' . $index . '.title') is-invalid @enderror"
                                               value="{{ old('languages.' . $index . '.title', $bannerTranslation->title ?? '') }}" required>
                                        @error('languages.' . $index . '.title') 
                                            <div class="invalid-feedback">{{ $message }}</div> 
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="languages[{{ $index }}][description]">{{ __('cms.banners.description') }}</label>
                                        <textarea name="languages[{{ $index }}][description]" 
                                                  class="form-control @error('languages.' . $index . '.description') is-invalid @enderror"
                                                  rows="3">{{ old('languages.' . $index . '.description', $bannerTranslation->description ?? '') }}</textarea>
                                        @error('languages.' . $index . '.description') 
                                            <div class="invalid-feedback">{{ $message }}</div> 
                                        @enderror
                                    </div> 
                                                                 
                                    <label class="form-label mt-2">{{ __('cms.banners.image') }} ({{ $language->code }})</label>

                                    <div class="input-group">
                                        <label for="image_file_{{ $language->code }}" class="btn btn-primary">
                                            {{ __('cms.banners.choose_file') }}
                                        </label>
                                        <input type="file" id="image_file_{{ $language->code }}" name="languages[{{ $index }}][image]" 
                                            class="d-none form-control @error('languages.' . $index . '.image') is-invalid @enderror"
                                            accept="image/*"
                                            onchange="updateFileName(this, '{{ $language->code }}'); previewImage(this, '{{ $language->code }}')">

                                        <span id="file-name-{{ $language->code }}" class="ms-2 text-muted">
                                            @if (!empty($bannerTranslation->image))
                                                {{ basename($bannerTranslation->image) }}
                                            @else
                                               
                                            @endif
                                        </span>
                                    </div>

                                    @error('languages.' . $index . '.image')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                        <img id="image_preview_img_{{ $language->code }}" 
                                        src="{{ !empty($bannerTranslation->image_url) ? Storage::url($bannerTranslation->image_url) : asset('images/placeholder.png') }}" 
                                        alt="{{ __('cms.banners.image_preview') }}" 
                                        class="img-thumbnail" style="max-width: 100px;">                                  
                                    <input type="hidden" name="languages[{{ $index }}][language_code]" value="{{ $language->code }}"> 

                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-danger">{{ __('cms.banners.no_languages_available') }}</p>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary mt-3">{{ __('cms.banners.save') }}</button>
                <a href="{{ route('admin.banners.index') }}" class="btn btn-secondary mt-3">{{ __('cms.banners.button_back') }}</a>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Hero Banner Preview</h6>
            <small class="text-muted">As it appears on homepage hero</small>
        </div>
        <div class="card-body">
            <div id="banner-preview" style="position:relative;border-radius:8px;overflow:hidden;height:320px;background:linear-gradient(135deg,#222,#444);">
                <div style="position:absolute;inset:0;">
                    <img id="bp-img" src="{{ !empty($bannerTranslation->image_url) ? Storage::url($bannerTranslation->image_url) : asset('images/juvo_logo_3d.png') }}" style="width:100%;height:100%;object-fit:cover;opacity:.85;" alt="">
                </div>
                <div style="position:absolute;left:40px;top:50%;transform:translateY(-50%);max-width:60%;z-index:2;color:#fff;">
                    <h1 id="bp-title" style="font-size:40px;font-weight:800;margin:0;text-shadow:2px 4px 12px rgba(0,0,0,.5);">{{ $bannerTranslation->title ?? 'Banner Title' }}</h1>
                    <p id="bp-desc" style="font-size:18px;margin-top:12px;text-shadow:2px 4px 12px rgba(0,0,0,.5);">{{ $bannerTranslation->description ?? '' }}</p>
                    <span class="btn btn-primary mt-3" style="background:#f68b1e;border-color:#f68b1e;">Shop Now</span>
                </div>
            </div>
            <div class="mt-2 small text-muted">Preview uses default language values; switch tab to see live edits.</div>
        </div>
    </div>
<script>
function updateFileName(input, langCode) {
    let fileNameSpan = document.getElementById('file-name-' + langCode);
    fileNameSpan.textContent = input.files.length > 0 ? input.files[0].name : '{{ __("cms.banners.no_file_chosen") }}';
}

function previewImage(input, langCode) {
    let previewDiv = document.getElementById('image_preview_' + langCode);
    let previewImg = document.getElementById('image_preview_img_' + langCode);
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewDiv.style.display = 'block';
            let bpImg = document.getElementById('bp-img');
            if (bpImg) bpImg.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

</script>
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const editors = {};

    document.querySelectorAll('textarea[name^="languages"]').forEach((el) => {
        const id = el.getAttribute('name').replace(/[\[\]\.]/g, '_'); 
        el.id = id;

        ClassicEditor.create(el)
            .then(editor => {
                editors[id] = editor;
                editor.model.document.on('change:data', function(){
                    let data = editor.getData().replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                    if (el.closest('.tab-pane') && el.closest('.tab-pane').classList.contains('active')) {
                        let bpDesc = document.getElementById('bp-desc');
                        if (bpDesc) bpDesc.textContent = data;
                    }
                });
            })
            .catch(error => {
                console.error('CKEditor initialization error:', error);
            });
    });

    document.querySelectorAll('input[name*="[title]"]').forEach(function(inp){
        inp.addEventListener('input', function(){
            if (inp.closest('.tab-pane') && inp.closest('.tab-pane').classList.contains('active')) {
                let bpTitle = document.getElementById('bp-title');
                if (bpTitle) bpTitle.textContent = inp.value;
            }
        });
    });

    document.querySelector('form').addEventListener('submit', function () {
        for (const id in editors) {
            const editor = editors[id];
            if (editor) {
                const textarea = document.getElementById(id);
                if (textarea) textarea.value = editor.getData();
            }
        }
    });
});
</script>
@endsection
