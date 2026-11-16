@extends('admin.layouts.admin')

@section('title', $slide->exists ? 'Edit Slide' : 'New Slide')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">{{ $slide->exists ? 'Edit Slide' : 'New Slide' }}</h4>
        <a href="{{ route('admin.homepage.slides.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger mt-3">
            <ul class="mb-0">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $slide->exists ? route('admin.homepage.slides.update', $slide->id) : route('admin.homepage.slides.store') }}">
        @csrf
        @if($slide->exists) @method('PUT') @endif

        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input name="title" class="form-control" value="{{ old('title', $slide->title) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subtitle</label>
                        <input name="subtitle" class="form-control" value="{{ old('subtitle', $slide->subtitle) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $slide->description) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Button label</label>
                        <input name="cta_label" class="form-control" value="{{ old('cta_label', $slide->cta_label) }}" placeholder="Shop Now">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Button destination URL</label>
                        <input name="cta_url" class="form-control" value="{{ old('cta_url', $slide->cta_url) }}" placeholder="/products or https://…">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Badge</label>
                        <input name="badge" class="form-control" value="{{ old('badge', $slide->badge) }}" placeholder="Sale">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Text align</label>
                        <select name="text_align" class="form-select">
                            @foreach(['left','center','right'] as $a)
                                <option value="{{ $a }}" {{ old('text_align', $slide->text_align ?: 'left') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12"><hr class="mt-4 mb-1"><h6 class="fw-bold mb-1">Banner size &amp; colours</h6>
                        <p class="text-muted small mb-0">Leave colours empty to use the Juvo default. The gradient runs from the first to the second colour.</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Banner height</label>
                        @php $heightVal = old('height', $slide->height ?: 'medium'); @endphp
                        <select name="height" class="form-select">
                            @foreach(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large', 'auto' => 'Auto (fit content)'] as $h => $lbl)
                                <option value="{{ $h }}" {{ $heightVal === $h ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Title size (px, optional)</label>
                        <input type="number" name="title_size" min="18" max="80" class="form-control"
                               value="{{ old('title_size', $slide->title_size) }}" placeholder="40">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Image overlay</label>
                        @php $overlayVal = old('overlay', $slide->overlay ?: 'medium'); @endphp
                        <select name="overlay" class="form-select">
                            @foreach(['none' => 'None', 'light' => 'Light', 'medium' => 'Medium', 'dark' => 'Dark'] as $o => $lbl)
                                <option value="{{ $o }}" {{ $overlayVal === $o ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Text colour</label>
                        <input type="color" name="text_color" class="form-control form-control-color"
                               value="{{ old('text_color', $slide->text_color) ?: '#ffffff' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Background colour (start)</label>
                        <input type="color" name="bg_color" class="form-control form-control-color"
                               value="{{ old('bg_color', $slide->bg_color) ?: '#2b2b2b' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Background colour (end, optional)</label>
                        <input type="color" name="bg_color_2" class="form-control form-control-color"
                               value="{{ old('bg_color_2', $slide->bg_color_2) ?: '#4a4a4a' }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Desktop image</label>
                        <input type="file" name="image" accept="image/*" class="form-control">
                        @if($slide->image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($slide->image) }}" alt="" class="mt-2 rounded" style="height:70px;">
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mobile image (optional)</label>
                        <input type="file" name="mobile_image" accept="image/*" class="form-control">
                        @if($slide->mobile_image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($slide->mobile_image) }}" alt="" class="mt-2 rounded" style="height:70px;">
                        @endif
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="published" {{ old('status', $slide->status ?: 'published') === 'published' ? 'selected' : '' }}>Published</option>
                            <option value="draft" {{ old('status', $slide->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Publish from (optional)</label>
                        <input type="datetime-local" name="starts_at" class="form-control"
                               value="{{ old('starts_at', optional($slide->starts_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Publish until (optional)</label>
                        <input type="datetime-local" name="ends_at" class="form-control"
                               value="{{ old('ends_at', optional($slide->ends_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 mb-5">
            <button class="btn btn-primary">{{ $slide->exists ? 'Save changes' : 'Create slide' }}</button>
            <a href="{{ route('admin.homepage.slides.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
