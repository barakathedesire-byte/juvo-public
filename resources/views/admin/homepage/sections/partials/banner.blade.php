<div class="border rounded p-3 mb-3 sec-banner" data-index="{{ $i }}">
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label small">Title</label>
            <input name="banners[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $banner['title'] ?? '' }}">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Subtitle</label>
            <input name="banners[{{ $i }}][subtitle]" class="form-control form-control-sm" value="{{ $banner['subtitle'] ?? '' }}">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Destination URL</label>
            <input name="banners[{{ $i }}][url]" class="form-control form-control-sm" value="{{ $banner['url'] ?? '' }}" placeholder="/products or https://…">
        </div>

        <div class="col-md-3"><label class="form-label small">Size</label>
            <select name="banners[{{ $i }}][size]" class="form-select form-select-sm">
                @foreach(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'wide' => 'Wide', 'tall' => 'Tall', 'full' => 'Full width'] as $val => $label)
                    <option value="{{ $val }}" {{ ($banner['size'] ?? 'md') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Edges</label>
            <select name="banners[{{ $i }}][edges]" class="form-select form-select-sm">
                @foreach(['none' => 'Square', 'sm' => 'Slight', 'md' => 'Rounded', 'lg' => 'Big', 'pill' => 'Pill'] as $val => $label)
                    <option value="{{ $val }}" {{ ($banner['edges'] ?? 'md') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Shape</label>
            <select name="banners[{{ $i }}][shape]" class="form-select form-select-sm">
                @foreach(['rect' => 'Rectangle', 'rounded' => 'Rounded', 'pill' => 'Pill', 'circle' => 'Circle', 'hex' => 'Hexagon'] as $val => $label)
                    <option value="{{ $val }}" {{ ($banner['shape'] ?? 'rounded') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Effect</label>
            <select name="banners[{{ $i }}][effect]" class="form-select form-select-sm">
                @foreach(['none' => 'None', 'shadow' => 'Shadow', 'glow' => 'Glow', 'overlay' => 'Dark overlay', 'zoom' => 'Hover zoom', 'parallax' => 'Parallax'] as $val => $label)
                    <option value="{{ $val }}" {{ ($banner['effect'] ?? 'none') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Background</label>
            <input type="color" name="banners[{{ $i }}][bg_color]" class="form-control form-control-color form-control-sm" value="{{ $banner['bg_color'] ?? '#282828' }}">
        </div>

        <div class="col-md-2"><label class="form-label small">Text colour</label>
            <input type="color" name="banners[{{ $i }}][text_color]" class="form-control form-control-color form-control-sm" value="{{ $banner['text_color'] ?? '#ffffff' }}">
        </div>
        <div class="col-md-2"><label class="form-label small">Gradient from</label>
            <input type="color" name="banners[{{ $i }}][gradient_from]" class="form-control form-control-color form-control-sm" value="{{ $banner['gradient_from'] ?? '#f68b1e' }}">
        </div>
        <div class="col-md-2"><label class="form-label small">Gradient to</label>
            <input type="color" name="banners[{{ $i }}][gradient_to]" class="form-control form-control-color form-control-sm" value="{{ $banner['gradient_to'] ?? '#d9760c' }}">
        </div>

        <div class="col-md-3"><label class="form-label small">Image</label>
            <input type="file" name="banners[{{ $i }}][image]" accept="image/*" class="form-control form-control-sm">
            @if(! empty($banner['image']))
                <input type="hidden" name="banners[{{ $i }}][existing_image]" value="{{ $banner['image'] }}">
                <img src="{{ \Illuminate\Support\Str::startsWith($banner['image'], ['http://','https://']) ? $banner['image'] : \Illuminate\Support\Facades\Storage::url($banner['image']) }}" style="height:40px;border-radius:4px;margin-top:4px;" alt="">
            @endif
        </div>
        <div class="col-md-8"><label class="form-label small">…or image URL</label>
            <input name="banners[{{ $i }}][image_url]" class="form-control form-control-sm" value="{{ $banner['image_url'] ?? '' }}" placeholder="https://…">
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-banner">Remove banner</button>
        </div>
    </div>
</div>
