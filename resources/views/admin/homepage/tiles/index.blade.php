@extends('admin.layouts.admin')

@section('title', 'Collection Tiles')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Collection Tiles</h4>
        <div>
            <a href="{{ route('admin.homepage.collections.index') }}" class="btn btn-outline-primary btn-sm">Manage collections</a>
            <a href="{{ route('admin.homepage.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @php
        $collections = ['new_arrivals' => 'New arrivals', 'clearance' => 'Clearance / final markdowns', 'hidden_gems' => 'Curated discoveries', 'deals' => 'Discounted deals', 'featured' => 'Featured products'];
        $imgUrl = function ($p) { return $p ? (\Illuminate\Support\Str::startsWith($p, ['http://','https://']) ? $p : \Illuminate\Support\Facades\Storage::url($p)) : null; };
    @endphp

    <div class="row g-3 mt-1" id="tile-rows" data-reorder-url="{{ route('admin.homepage.tiles.reorder') }}">
        @foreach($tiles as $tile)
            <div class="col-lg-6" draggable="true" data-id="{{ $tile->id }}">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-grip-vertical me-2 text-muted"></i>{{ $tile->title }}</span>
                        <span class="badge {{ $tile->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ $tile->status }}</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.homepage.tiles.update', $tile->id) }}" enctype="multipart/form-data">
                            @csrf @method('PUT')
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small">Title</label>
                                    <input name="title" class="form-control form-control-sm" value="{{ $tile->title }}" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Subtitle</label>
                                    <input name="subtitle" class="form-control form-control-sm" value="{{ $tile->subtitle }}">
                                </div>
                                <div class="col-5">
                                    <label class="form-label small">Links to</label>
                                    <select name="link_type" class="form-select form-select-sm">
                                        <option value="collection" {{ $tile->link_type === 'collection' ? 'selected' : '' }}>Collection preset</option>
                                        <option value="category" {{ $tile->link_type === 'category' ? 'selected' : '' }}>Category</option>
                                        <option value="url" {{ $tile->link_type === 'url' ? 'selected' : '' }}>Custom URL</option>
                                    </select>
                                </div>
                                <div class="col-7">
                                    <label class="form-label small">Destination</label>
                                    <input name="link_value" class="form-control form-control-sm" value="{{ $tile->link_value }}" list="tile-destinations" placeholder="new_arrivals / baby / /products">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Status</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="published" {{ $tile->status === 'published' ? 'selected' : '' }}>Published</option>
                                        <option value="draft" {{ $tile->status === 'draft' ? 'selected' : '' }}>Draft</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label small">Background</label>
                                    <input type="color" name="bg_color" class="form-control form-control-sm form-control-color" value="{{ $tile->bg_color ?: '#ffffff' }}">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small">Text</label>
                                    <input type="color" name="text_color" class="form-control form-control-sm form-control-color" value="{{ $tile->text_color ?: '#1d1e24' }}">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small">Size</label>
                                    <select name="size" class="form-select form-select-sm">
                                        @foreach(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $value => $label)
                                            <option value="{{ $value }}" @selected(($tile->size ?: 'md') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Shape</label>
                                    <select name="shape" class="form-select form-select-sm">
                                        @foreach(['circle' => 'Circle', 'rounded' => 'Rounded', 'square' => 'Square'] as $value => $label)
                                            <option value="{{ $value }}" @selected(($tile->shape ?: 'circle') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Replace image</label>
                                    <input type="file" name="image" accept="image/*" class="form-control form-control-sm">
                                </div>
                                @if($imgUrl($tile->image))
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove-image-{{ $tile->id }}">
                                        <label class="form-check-label small" for="remove-image-{{ $tile->id }}">Remove current image</label>
                                    </div>
                                </div>
                                @endif
                            </div>
                            <div class="mt-2 d-flex align-items-center gap-2">
                                @if($imgUrl($tile->image))
                                    <img src="{{ $imgUrl($tile->image) }}" alt="" style="width:48px;height:48px;border-radius:{{ ($tile->shape ?: 'circle') === 'circle' ? '50%' : (($tile->shape ?: 'circle') === 'rounded' ? '12px' : '6px') }};object-fit:cover;">
                                @endif
                                <button class="btn btn-sm btn-primary">Save</button>
                            </div>
                        </form>
                        <div class="mt-2 d-flex gap-2">
                            <form method="POST" action="{{ route('admin.homepage.tiles.toggle', $tile->id) }}">
                                @csrf<button class="btn btn-sm btn-outline-secondary">{{ $tile->status === 'published' ? 'Unpublish' : 'Publish' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.homepage.tiles.destroy', $tile->id) }}"
                                  onsubmit="return confirm('Delete this tile?');">
                                @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <datalist id="tile-destinations">
        @foreach($collections as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        @isset($namedCollections)
            @foreach($namedCollections as $c)<option value="{{ $c->slug }}">{{ $c->name }} (collection)</option>@endforeach
        @endisset
        @foreach($categories as $c)<option value="{{ $c->slug }}">{{ $c->translation->name ?? $c->slug }}</option>@endforeach
    </datalist>

    <div class="card shadow-sm mt-3 mb-5">
        <div class="card-header">Add a tile</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.homepage.tiles.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label small">Title</label><input name="title" class="form-control form-control-sm" required></div>
                    <div class="col-md-3"><label class="form-label small">Subtitle</label><input name="subtitle" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><label class="form-label small">Links to</label>
                        <select name="link_type" class="form-select form-select-sm">
                            <option value="collection">Collection preset</option>
                            <option value="category">Category</option>
                            <option value="url">Custom URL</option>
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label small">Destination</label><input name="link_value" class="form-control form-control-sm" list="tile-destinations"></div>
                    <div class="col-md-2"><label class="form-label small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="published" {{ old('status', 'published') === 'published' ? 'selected' : '' }}>Published</option>
                            <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label small">Background</label><input type="color" name="bg_color" class="form-control form-control-sm form-control-color" value="#ffffff"></div>
                    <div class="col-md-2"><label class="form-label small">Text</label><input type="color" name="text_color" class="form-control form-control-sm form-control-color" value="#1d1e24"></div>
                    <div class="col-md-2"><label class="form-label small">Size</label>
                        <select name="size" class="form-select form-select-sm">
                            <option value="sm">Small</option>
                            <option value="md" selected>Medium</option>
                            <option value="lg">Large</option>
                        </select></div>
                    <div class="col-md-2"><label class="form-label small">Shape</label>
                        <select name="shape" class="form-select form-select-sm">
                            <option value="circle" selected>Circle</option>
                            <option value="rounded">Rounded</option>
                            <option value="square">Square</option>
                        </select></div>
                    <div class="col-md-2"><label class="form-label small">Image</label><input type="file" name="image" accept="image/*" class="form-control form-control-sm"></div>
                </div>
                <button class="btn btn-sm btn-primary mt-2">Add tile</button>
            </form>
        </div>
    </div>
@endsection

@section('js')
<script>
(function () {
    var wrap = document.getElementById('tile-rows');
    if (!wrap) return;
    var url = wrap.getAttribute('data-reorder-url');
    var dragEl = null;
    wrap.querySelectorAll('[draggable]').forEach(function (col) {
        col.addEventListener('dragstart', function () { dragEl = col; });
        col.addEventListener('dragend', function () { save(); });
        col.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragEl || dragEl === col) return;
            var rect = col.getBoundingClientRect();
            var after = (e.clientX - rect.left) > rect.width / 2;
            if (after) { col.after(dragEl); } else { col.before(dragEl); }
        });
    });
    function save() {
        var order = Array.prototype.map.call(wrap.querySelectorAll('[data-id]'), function (c) { return c.getAttribute('data-id'); });
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ order: order })
        }).then(function (r) { if (r.ok) toastr.success('Order saved'); });
    }
})();
</script>
@endsection
