@extends('vendor.layouts.master')

@section('title', 'My Store')
@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0"><i class="fas fa-store me-2" style="color:#f68b1e;"></i>My Store</h4>
        <a href="{{ route('store.show', $shop->slug) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-external-link-alt me-1"></i> View public storefront
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('vendor.store.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-header card-header-bg">
                        <h6 class="mb-0 text-white"><i class="fas fa-id-card me-1"></i> Store identity</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Store name</label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name', $shop->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Store address (slug)</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ url('/store') }}/</span>
                                <input type="text" name="slug" class="form-control"
                                       value="{{ old('slug', $shop->slug) }}">
                            </div>
                            <div class="form-text">Letters, numbers and dashes only. Changing this changes your public URL.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Short description</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="2000">{{ old('description', $shop->description) }}</textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Our story</label>
                            <textarea name="story" class="form-control" rows="5" maxlength="8000"
                                      placeholder="Tell shoppers about your brand, mission and what makes you special.">{{ old('story', $shop->story) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header card-header-bg">
                        <h6 class="mb-0 text-white"><i class="fas fa-image me-1"></i> Branding</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 text-center">
                            @if ($shop->banner)
                                <img src="{{ Storage::disk('public')->url($shop->banner) }}" alt="Banner"
                                     class="img-fluid rounded mb-2" style="max-height:120px;object-fit:cover;width:100%;">
                            @endif
                            <label class="form-label d-block text-start">Banner</label>
                            <input type="file" name="banner" class="form-control" accept="image/*">
                        </div>
                        <div class="text-center">
                            @if ($shop->logo)
                                <img src="{{ Storage::disk('public')->url($shop->logo) }}" alt="Logo"
                                     class="rounded mb-2" style="width:80px;height:80px;object-fit:cover;">
                            @endif
                            <label class="form-label d-block text-start">Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header card-header-bg">
                        <h6 class="mb-0 text-white"><i class="fas fa-headset me-1"></i> Support contact</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Support email</label>
                            <input type="email" name="support_email" class="form-control"
                                   value="{{ old('support_email', $shop->support_email) }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Support phone</label>
                            <input type="text" name="support_phone" class="form-control"
                                   value="{{ old('support_phone', $shop->support_phone) }}">
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header card-header-bg">
                        <h6 class="mb-0 text-white"><i class="fas fa-umbrella-beach me-1"></i> Vacation mode</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="vacation_mode" value="1"
                                   id="vacation_mode" {{ old('vacation_mode', $shop->vacation_mode) ? 'checked' : '' }}>
                            <label class="form-check-label" for="vacation_mode">Pause store (show notice to shoppers)</label>
                        </div>
                        <input type="text" name="vacation_note" class="form-control" maxlength="255"
                               placeholder="Notice message (optional)"
                               value="{{ old('vacation_note', $shop->vacation_note) }}">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save me-1"></i> Save store profile
                </button>
            </div>
        </div>
    </form>
@endsection
