@extends('admin.layouts.admin')

@section('title', 'Homepage Settings')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Homepage Settings</h4>
        <a href="{{ route('admin.homepage.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.homepage.settings.update') }}">
                @csrf
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="autoplay" value="1" id="autoplay"
                           {{ $values['autoplay'] === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="autoplay">Autoplay hero carousel</label>
                </div>

                <div class="mb-3">
                    <label class="form-label">Slide duration (ms)</label>
                    <input type="number" name="autoplay_speed" min="2000" max="15000" step="500"
                           class="form-control @error('autoplay_speed') is-invalid @enderror"
                           value="{{ old('autoplay_speed', $values['autoplay_speed']) }}">
                    @error('autoplay_speed')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Between 2000 and 15000 milliseconds.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="show_category_strip" value="1" id="show_category_strip"
                           {{ $values['show_category_strip'] === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="show_category_strip">Show category strip in navigation</label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="show_category_sidebar" value="1" id="show_category_sidebar"
                           {{ ($values['show_category_sidebar'] ?? '1') === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="show_category_sidebar">Show the "All Categories" sidebar</label>
                    <div class="form-text">When on, the header "All Categories" button opens the category sidebar on every screen size.</div>
                </div>

                <hr class="my-4">
                <h6 class="fw-bold mb-1">Colours &amp; effects</h6>
                <p class="text-muted small mb-3">Leave a colour empty to keep the Juvo default. Effects add motion and depth to the storefront.</p>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Page background</label>
                        <input type="color" name="home_bg_color" class="form-control form-control-color"
                               value="{{ $values['home_bg_color'] ?: '#f1f1f2' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Card background</label>
                        <input type="color" name="home_card_color" class="form-control form-control-color"
                               value="{{ $values['home_card_color'] ?: '#ffffff' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Body text</label>
                        <input type="color" name="home_text_color" class="form-control form-control-color"
                               value="{{ $values['home_text_color'] ?: '#313133' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Accent (buttons, links)</label>
                        <input type="color" name="home_accent_color" class="form-control form-control-color"
                               value="{{ $values['home_accent_color'] ?: '#f68b1e' }}">
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center mt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-jv-preset="#f1f1f2,#ffffff,#313133,#f68b1e">Juvo default</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" data-jv-preset="#f5f5f7,#ffffff,#1f2937,#7c3aed">Violet nights</button>
                    <button type="button" class="btn btn-sm btn-outline-success" data-jv-preset="#eef7f0,#ffffff,#173a25,#0f9d58">Fresh green</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-jv-preset="#fdf2f2,#ffffff,#3a1717,#e03131">Flash red</button>
                </div>

                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="home_effects" value="1" id="home_effects"
                           {{ $values['home_effects'] === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="home_effects">Enable motion effects (gradient glow, card lift, reveal)</label>
                </div>

                <button class="btn btn-primary mt-4">Save settings</button>
            </form>
        </div>
    </div>

    <script>
    (function () {
        document.querySelectorAll('[data-jv-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var parts = btn.getAttribute('data-jv-preset').split(',');
                ['home_bg_color', 'home_card_color', 'home_text_color', 'home_accent_color'].forEach(function (name, i) {
                    var el = document.querySelector('[name="' + name + '"]');
                    if (el && parts[i]) { el.value = parts[i]; }
                });
            });
        });
    })();
    </script>
@endsection
