<form method="POST" action="{{ route('admin.admin-settings.general.update') }}">
    @csrf
    @method('PUT')

    <div class="card border-0 shadow-sm">
        <div class="card-header py-2" style="background:var(--juvo-primary,#f68b1e)">
            <strong class="text-white"><i class="fas fa-globe me-1"></i>Site identity &amp; contact</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="g-site_name">Site name</label>
                    <input type="text" class="form-control form-control-sm" id="g-site_name" name="site_name"
                           value="{{ old('site_name', $site->site_name ?? 'Juvo') }}" required>
                    @error('site_name')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label small mb-1" for="g-tagline">Tagline</label>
                    <input type="text" class="form-control form-control-sm" id="g-tagline" name="tagline"
                           value="{{ old('tagline', $site->tagline ?? '') }}">
                    @error('tagline')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label small mb-1" for="g-contact_email">Contact email</label>
                    <input type="email" class="form-control form-control-sm" id="g-contact_email" name="contact_email"
                           value="{{ old('contact_email', $site->contact_email ?? '') }}">
                    @error('contact_email')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label small mb-1" for="g-contact_phone">Contact phone</label>
                    <input type="text" class="form-control form-control-sm" id="g-contact_phone" name="contact_phone"
                           value="{{ old('contact_phone', $site->contact_phone ?? '') }}">
                    @error('contact_phone')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label small mb-1" for="g-address">Address</label>
                    <textarea class="form-control form-control-sm" id="g-address" name="address" rows="2">{{ old('address', $site->address ?? '') }}</textarea>
                    @error('address')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label small mb-1" for="g-footer_text">Footer text</label>
                    <textarea class="form-control form-control-sm" id="g-footer_text" name="footer_text" rows="2">{{ old('footer_text', $site->footer_text ?? '') }}</textarea>
                    @error('footer_text')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header py-2" style="background:var(--juvo-primary,#f68b1e)">
            <strong class="text-white"><i class="fas fa-magnifying-glass me-1"></i>Search metadata</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="g-meta_title">Meta title</label>
                    <input type="text" class="form-control form-control-sm" id="g-meta_title" name="meta_title"
                           value="{{ old('meta_title', $site->meta_title ?? '') }}">
                    @error('meta_title')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label small mb-1" for="g-meta_keywords">Meta keywords</label>
                    <input type="text" class="form-control form-control-sm" id="g-meta_keywords" name="meta_keywords"
                           value="{{ old('meta_keywords', $site->meta_keywords ?? '') }}">
                    @error('meta_keywords')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label small mb-1" for="g-meta_description">Meta description</label>
                    <textarea class="form-control form-control-sm" id="g-meta_description" name="meta_description" rows="2">{{ old('meta_description', $site->meta_description ?? '') }}</textarea>
                    @error('meta_description')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn text-white" style="background:var(--juvo-primary,#f68b1e)">
            <i class="fas fa-floppy-disk me-1"></i>Save general settings
        </button>
    </div>
</form>
