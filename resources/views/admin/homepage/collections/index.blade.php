@extends('admin.layouts.admin')

@section('title', 'Collections')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Collections</h4>
        <div>
            <a href="{{ route('admin.homepage.tiles.index') }}" class="btn btn-outline-secondary btn-sm">Collection tiles</a>
            <a href="{{ route('admin.homepage.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <p class="text-muted mt-2">Define named collections here, then point a collection tile at them. The collection slug is what you enter as the tile destination.</p>

    <div class="card shadow-sm mt-2">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug (tile destination)</th>
                        <th>Rule</th>
                        <th>Rule value</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($collections as $collection)
                        <tr>
                            <td colspan="6" class="p-0">
                                <form method="POST" action="{{ route('admin.homepage.collections.update', $collection->id) }}" enctype="multipart/form-data" class="row g-2 align-items-center px-3 py-2 m-0">
                                    @csrf @method('PUT')
                                    <div class="col-md-2"><input name="name" class="form-control form-control-sm" value="{{ $collection->name }}" required></div>
                                    <div class="col-md-2"><input name="slug" class="form-control form-control-sm" value="{{ $collection->slug }}"></div>
                                    <div class="col-md-1">
                                        <input type="color" name="bg_color" class="form-control form-control-sm form-control-color" value="{{ $collection->bg_color ?: '#f68b1e' }}" title="Background">
                                    </div>
                                    <div class="col-md-1">
                                        <input type="color" name="text_color" class="form-control form-control-sm form-control-color" value="{{ $collection->text_color ?: '#ffffff' }}" title="Text">
                                    </div>
                                    <div class="col-md-1">
                                        <select name="size" class="form-select form-select-sm">
                                            @foreach(['sm' => 'S', 'md' => 'M', 'lg' => 'L'] as $value => $label)
                                                <option value="{{ $value }}" @selected(($collection->size ?: 'md') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <select name="shape" class="form-select form-select-sm">
                                            @foreach(['circle' => 'O', 'rounded' => 'R', 'square' => 'S'] as $value => $label)
                                                <option value="{{ $value }}" @selected(($collection->shape ?: 'rounded') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        @if($collection->image)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($collection->image) }}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;" class="mb-1 d-block">
                                        @endif
                                        <input type="file" name="image" accept="image/*" class="form-control form-control-sm">
                                        @if($collection->image)
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove-collection-image-{{ $collection->id }}">
                                                <label class="form-check-label small" for="remove-collection-image-{{ $collection->id }}">Remove image</label>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-md-3">
                                        <select name="rule_type" class="form-select form-select-sm">
                                            @foreach($rules as $key => $label)
                                                <option value="{{ $key }}" {{ $collection->rule_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2"><input name="rule_value" class="form-control form-control-sm" list="collection-values" value="{{ $collection->rule_value }}" placeholder="category slug / brand id"></div>
                                    <div class="col-md-1">
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="active" {{ $collection->status === 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ $collection->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <button class="btn btn-sm btn-primary">Save</button>
                                        <span class="border border-danger rounded-3 d-inline-block ms-1 px-1" title="Delete" onclick="if(confirm('Delete this collection?')) document.getElementById('del-{{ $collection->id }}').submit();" style="cursor:pointer;"><i class="bi bi-trash-fill text-danger"></i></span>
                                    </div>
                                </form>
                                <form id="del-{{ $collection->id }}" method="POST" action="{{ route('admin.homepage.collections.destroy', $collection->id) }}" class="d-none">@csrf @method('DELETE')</form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No collections yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <datalist id="collection-values">
        @foreach($categories as $c)<option value="{{ $c->slug }}">{{ $c->translation->name ?? $c->slug }} (category)</option>@endforeach
        @foreach($brands as $b)<option value="{{ $b->id }}">{{ $b->translation->name ?? ('Brand #'.$b->id) }}</option>@endforeach
    </datalist>

    <div class="card shadow-sm mt-3 mb-5">
        <div class="card-header">Add a collection</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.homepage.collections.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label class="form-label small">Slug (optional)</label><input name="slug" class="form-control form-control-sm" placeholder="auto from name"></div>
                    <div class="col-md-1"><label class="form-label small">BG</label><input type="color" name="bg_color" class="form-control form-control-sm form-control-color" value="#f68b1e"></div>
                    <div class="col-md-1"><label class="form-label small">Text</label><input type="color" name="text_color" class="form-control form-control-sm form-control-color" value="#ffffff"></div>
                    <div class="col-md-1"><label class="form-label small">Size</label>
                        <select name="size" class="form-select form-select-sm">
                            <option value="sm">S</option>
                            <option value="md" selected>M</option>
                            <option value="lg">L</option>
                        </select></div>
                    <div class="col-md-1"><label class="form-label small">Shape</label>
                        <select name="shape" class="form-select form-select-sm">
                            <option value="circle">O</option>
                            <option value="rounded" selected>R</option>
                            <option value="square">S</option>
                        </select></div>
                    <div class="col-md-2"><label class="form-label small">Image</label><input type="file" name="image" accept="image/*" class="form-control form-control-sm"></div>
                    <div class="col-md-3">
                        <label class="form-label small">Rule</label>
                        <select name="rule_type" class="form-select form-select-sm">
                            @foreach($rules as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label small">Rule value</label><input name="rule_value" class="form-control form-control-sm" list="collection-values" placeholder="category slug / brand id"></div>
                    <div class="col-md-1"><label class="form-label small">Status</label>
                        <select name="status" class="form-select form-control-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Add collection</button></div>
                </div>
            </form>
        </div>
    </div>
@endsection
