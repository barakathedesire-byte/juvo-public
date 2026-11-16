@extends('admin.layouts.admin')

@section('title', $section->exists ? 'Edit Section' : 'New Section')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">{{ $section->exists ? 'Edit Section' : 'New Section' }}</h4>
        <a href="{{ route('admin.homepage.sections.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $section->exists ? route('admin.homepage.sections.update', $section->id) : route('admin.homepage.sections.store') }}">
        @csrf
        @if($section->exists) @method('PUT') @endif

        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Section type</label>
                        <select name="type" id="sec-type" class="form-select">
                            @foreach([
                                'product_carousel' => 'Product carousel',
                                'product_grid' => 'Product grid',
                                'collection_carousel' => 'Collections (carousel)',
                                'collection_grid' => 'Collections (grid)',
                                'promo_grid' => 'Promotional grid',
                                'image' => 'Picture',
                                'gallery' => 'Picture gallery',
                                'slides' => 'Slides / banner carousel',
                                'announcement' => 'Announcement',
                            ] as $val => $label)
                                <option value="{{ $val }}" {{ old('type', $section->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Title <span class="text-muted small">(optional for pictures and announcements)</span></label>
                        <input name="title" class="form-control" value="{{ old('title', $section->title) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Icon (optional)</label>
                        <input name="icon" class="form-control" value="{{ old('icon', $section->icon) }}" placeholder="fas fa-fire">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Subtitle</label>
                        <input name="subtitle" class="form-control" value="{{ old('subtitle', $section->subtitle) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Layout</label>
                        <select name="layout" id="sec-layout" class="form-select">
                            <option value="carousel" {{ old('layout', $section->layout) === 'carousel' ? 'selected' : '' }}>Carousel</option>
                            <option value="grid" {{ old('layout', $section->layout) === 'grid' ? 'selected' : '' }}>Grid</option>
                            <option value="rows" {{ old('layout', $section->layout) === 'rows' ? 'selected' : '' }}>Multiple rows</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3 sec-media-fields">
            <div class="card-header">Picture</div>
            <div class="card-body">
                @php
                    $existingImage = old('existing_image', $section->settings['image'] ?? null);
                    $existingImageUrl = $existingImage
                        ? (\Illuminate\Support\Str::startsWith($existingImage, ['http://', 'https://']) ? $existingImage : \Illuminate\Support\Facades\Storage::url($existingImage))
                        : null;
                @endphp
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Upload picture</label>
                        <input type="file" name="image" accept="image/*" class="form-control">
                        <input type="hidden" name="existing_image" value="{{ $existingImage }}">
                        @if($existingImageUrl)
                            <img src="{{ $existingImageUrl }}" alt="" style="height:56px;border-radius:6px;margin-top:6px;" class="d-block">
                        @endif
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">…or image URL</label>
                        <input name="image_url" class="form-control" value="{{ old('image_url') }}" placeholder="https://…">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="image_rounded" value="0">
                            <input class="form-check-input" type="checkbox" name="image_rounded" value="1" id="sec-image-rounded"
                                   {{ old('image_rounded', $section->settings['rounded'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="sec-image-rounded">Rounded corners</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Link (optional)</label>
                        <input name="image_link" class="form-control" value="{{ old('image_link', $section->settings['link'] ?? '') }}" placeholder="/products or https://…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Caption</label>
                        <input name="image_caption" class="form-control" value="{{ old('image_caption', $section->settings['caption'] ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Alt text</label>
                        <input name="image_alt" class="form-control" value="{{ old('image_alt', $section->settings['alt'] ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3 sec-announcement-fields">
            <div class="card-header">Announcement</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Message</label>
                        <textarea name="announcement_body" rows="3" class="form-control" placeholder="Tell shoppers about a sale, delivery update or other news.">{{ old('announcement_body', $section->settings['body'] ?? '') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tone</label>
                        <select name="announcement_tone" class="form-select">
                            @foreach(['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'promo' => 'Promotion'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('announcement_tone', $section->settings['tone'] ?? 'info') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Button label</label>
                        <input name="announcement_cta_label" class="form-control" value="{{ old('announcement_cta_label', $section->settings['cta_label'] ?? '') }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Button link</label>
                        <input name="announcement_cta_url" class="form-control" value="{{ old('announcement_cta_url', $section->settings['cta_url'] ?? '') }}" placeholder="/products or https://…">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3 sec-product-fields">
            <div class="card-header">Product source</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Source</label>
                        <select name="source" class="form-select">
                            @foreach($sources as $val => $label)
                                <option value="{{ $val }}" {{ old('source', $section->source) === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category (only when source = Specific category)</label>
                        <select name="category_id" class="form-select">
                            @include('admin.partials.category-options', ['categories' => $categories, 'selected' => old('category_id', $section->category_id), 'placeholder' => '—'])
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Collection (only when source = Named collection)</label>
                        <select name="collection" id="sec-collection" class="form-select">
                            <option value="">—</option>
                            @foreach(($collections ?? []) as $col)
                                <option value="{{ $col->slug }}" {{ old('collection', $section->settings['collection'] ?? '') === $col->slug ? 'selected' : '' }}>
                                    {{ $col->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Max products</label>
                        <input type="number" name="limit" min="1" max="24" class="form-control" value="{{ old('limit', $section->limit ?: 10) }}">
                    </div>
                    <div class="col-12 sec-manual-fields">
                        <label class="form-label">Hand-picked products <span class="text-muted small">(only when source = Hand-picked products)</span></label>
                        @php $selectedProducts = old('products', $section->settings['product_ids'] ?? []); @endphp
                        <select name="products[]" class="form-select" multiple size="8">
                            @foreach(($products ?? []) as $p)
                                <option value="{{ $p->id }}" {{ in_array($p->id, (array) $selectedProducts) ? 'selected' : '' }}>
                                    {{ $p->translation->name ?? ('Product #'.$p->id) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="small text-muted mb-0">Hold Ctrl/Cmd to select several. Products display in the order you pick them; the list above is newest first.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3 sec-collection-fields">
            <div class="card-header">Collections</div>
            <div class="card-body">
                @php
                    $collectionSource = old('collection_source', $section->settings['collection_source'] ?? 'collections');
                    $selectedCollections = old('collections', $section->settings['collections'] ?? []);
                    $selectedCollectionCategory = old('collection_category_id', $section->settings['collection_category_id'] ?? null);
                @endphp
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Source</label>
                        <select name="collection_source" id="sec-collection-source" class="form-select">
                            <option value="collections" {{ $collectionSource === 'collections' ? 'selected' : '' }}>Named collections</option>
                            <option value="category" {{ $collectionSource === 'category' ? 'selected' : '' }}>Category — show its sub-categories</option>
                            <option value="subcategory" {{ $collectionSource === 'subcategory' ? 'selected' : '' }}>Sub-category — show deeper sub-categories</option>
                        </select>
                    </div>
                    <div class="col-md-4 sec-collection-cat">
                        <label class="form-label">Parent category</label>
                        <select name="collection_category_id" id="sec-collection-category" class="form-select">
                            @include('admin.partials.category-options', ['categories' => $categories, 'selected' => $selectedCollectionCategory, 'placeholder' => '—'])
                        </select>
                        <p class="small text-muted mb-0">Cards are built from this category's children (or grandchildren for sub-category) and link to each category page.</p>
                    </div>
                    <div class="col-12 sec-collection-named">
                        <label class="form-label">Collections to show</label>
                        <select name="collections[]" class="form-select" multiple size="8">
                            @foreach(($collections ?? []) as $col)
                                <option value="{{ $col->slug }}" {{ in_array($col->slug, (array) $selectedCollections) ? 'selected' : '' }}>
                                    {{ $col->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="small text-muted mb-0">Hold Ctrl/Cmd to select several. Every selected collection is shown; each card uses that collection's own shape, size, name and picture from <a href="{{ route('admin.homepage.collections.index') }}">Manage collections</a>.</p>
                    </div>
                    <div class="col-md-3 sec-collection-category-only" @if($collectionSource === 'collections') style="display:none" @endif>
                        <label class="form-label">Max cards (category sources)</label>
                        <input type="number" name="limit" min="1" max="24" class="form-control" value="{{ old('limit', $section->limit ?: 12) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Card background</label>
                        <input type="color" name="collection_bg_color" class="form-control form-control-color" value="{{ old('collection_bg_color', $section->settings['collection_bg_color'] ?? '#ffffff') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Card text</label>
                        <input type="color" name="collection_text_color" class="form-control form-control-color" value="{{ old('collection_text_color', $section->settings['collection_text_color'] ?? '#1d1e24') }}">
                    </div>
                    <div class="col-md-3 sec-collection-category-only" @if($collectionSource === 'collections') style="display:none" @endif>
                        <label class="form-label">Card size (category cards)</label>
                        <select name="collection_size" class="form-select">
                            @foreach(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('collection_size', $section->settings['collection_size'] ?? 'md') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 sec-collection-category-only" @if($collectionSource === 'collections') style="display:none" @endif>
                        <label class="form-label">Card shape (category cards)</label>
                        <select name="collection_shape" class="form-select">
                            @foreach(['circle' => 'Circle', 'rounded' => 'Rounded', 'square' => 'Square'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('collection_shape', $section->settings['collection_shape'] ?? 'rounded') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Collection names and pictures</label>
                    <p class="small text-muted mb-2">Override the name shown on each collection card, and optionally upload a picture. Uploaded images override the collection's own image.</p>
                    @php
                        $pictureSource = old('collection_source', $section->settings['collection_source'] ?? 'collections');
                        $pictureCategoryId = (int) old('collection_category_id', $section->settings['collection_category_id'] ?? 0);
                        $pictureTargets = collect();
                        if (in_array($pictureSource, ['category', 'subcategory'], true) && $pictureCategoryId > 0) {
                            $pictureParent = \App\Models\Category::with('children.translation', 'children.children.translation')->find($pictureCategoryId);
                            if ($pictureParent) {
                                $pictureTargets = $pictureSource === 'subcategory'
                                    ? $pictureParent->children->flatMap(fn ($child) => $child->children)->values()
                                    : $pictureParent->children->values();
                            }
                        } else {
                            $pictureTargets = collect($collections ?? [])->map(fn ($collection) => (object) ['slug' => $collection->slug, 'name' => $collection->name]);
                        }
                        $pictureTargets = $pictureTargets->map(fn ($target) => (object) ['slug' => $target->slug ?? $target->slug, 'name' => $target->name ?? ($target->translation->name ?? $target->slug)]);
                    @endphp
                    <div class="row g-2">
                        @foreach($pictureTargets as $collection)
                            @php
                                $existingImage = $section->settings['collection_images'][$collection->slug] ?? null;
                                $imgUrl = $existingImage ? (\Illuminate\Support\Str::startsWith($existingImage, ['http://','https://']) ? $existingImage : \Illuminate\Support\Facades\Storage::url($existingImage)) : null;
                                $existingName = $section->settings['collection_names'][$collection->slug] ?? null;
                            @endphp
                            <div class="col-md-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="small fw-semibold mb-1">{{ $collection->name }}</div>
                                    <input type="text" name="collection_names[{{ $collection->slug }}]" class="form-control form-control-sm mb-1" value="{{ old('collection_names.'.$collection->slug, $existingName) }}" placeholder="{{ $collection->name }}">
                                    @if($imgUrl)
                                        <img src="{{ $imgUrl }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" class="mb-1">
                                    @endif
                                    <input type="file" name="collection_images[{{ $collection->slug }}]" accept="image/*" class="form-control form-control-sm">
                                    <input type="hidden" name="existing_collection_images[{{ $collection->slug }}]" value="{{ $existingImage }}">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3 sec-rows-fields">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Product rows</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-row"><i class="fas fa-plus me-1"></i>Add row</button>
            </div>
            <div class="card-body">
                <p class="small text-muted">Stack several rows of product cards under one section. Each row has its own source, title and column counts.</p>
                <div id="rows-wrap">
                    @php $rows = old('rows', $section->settings['rows'] ?? []); @endphp
                    @foreach($rows as $i => $row)
                        @include('admin.homepage.sections.partials.row', ['i' => $i, 'row' => $row, 'sources' => $sources, 'categories' => $categories, 'collections' => $collections ?? []])
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3 sec-promo-fields">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Promotional cards</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-card"><i class="fas fa-plus me-1"></i>Add card</button>
            </div>
            <div class="card-body">
                <div id="cards-wrap">
                    @php $cards = old('cards', $section->settings['cards'] ?? []); @endphp
                    @foreach($cards as $i => $card)
                        <div class="border rounded p-3 mb-3 promo-card" data-index="{{ $i }}">
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label small">Title</label><input name="cards[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $card['title'] ?? '' }}"></div>
                                <div class="col-md-4"><label class="form-label small">Subtitle</label><input name="cards[{{ $i }}][subtitle]" class="form-control form-control-sm" value="{{ $card['subtitle'] ?? '' }}"></div>
                                <div class="col-md-4"><label class="form-label small">Badge</label><input name="cards[{{ $i }}][badge]" class="form-control form-control-sm" value="{{ $card['badge'] ?? '' }}"></div>
                                <div class="col-md-4"><label class="form-label small">Button label</label><input name="cards[{{ $i }}][cta_label]" class="form-control form-control-sm" value="{{ $card['cta_label'] ?? 'Shop now' }}"></div>
                                <div class="col-md-4"><label class="form-label small">Destination URL</label><input name="cards[{{ $i }}][url]" class="form-control form-control-sm" value="{{ $card['url'] ?? '' }}"></div>
                                <div class="col-md-2"><label class="form-label small">Size</label>
                                    <select name="cards[{{ $i }}][size]" class="form-select form-select-sm">
                                        <option value="sm" {{ ($card['size'] ?? 'sm') === 'sm' ? 'selected' : '' }}>Small</option>
                                        <option value="lg" {{ ($card['size'] ?? '') === 'lg' ? 'selected' : '' }}>Large</option>
                                    </select>
                                </div>
                                <div class="col-md-2"><label class="form-label small">Theme</label>
                                    <select name="cards[{{ $i }}][theme]" class="form-select form-select-sm">
                                        @foreach(['light','orange','dark'] as $t)
                                            <option value="{{ $t }}" {{ ($card['theme'] ?? 'light') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label small">Image</label><input type="file" name="cards[{{ $i }}][image]" accept="image/*" class="form-control form-control-sm">
                                    @if(!empty($card['image']))
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($card['image']) }}" style="height:44px;border-radius:4px;" class="mt-1">
                                    @endif
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-card">Remove card</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="small text-muted mb-0">A "Large" card spans two rows of the promo grid. Use it for the main feature.</p>
            </div>
        </div>

        <div class="card shadow-sm mt-3" id="sec-banner-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Banners</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-banner"><i class="fas fa-plus me-1"></i>Add banner</button>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Attach one or more banners to this section. Each banner can have its own size, edges, shape, colour and effect. For <strong>Picture gallery</strong> and <strong>Slides</strong> sections these banners are the pictures.</p>
                <div id="banners-wrap">
                    @php $formBanners = old('banners', $section->settings['banners'] ?? []); @endphp
                    @foreach($formBanners as $i => $banner)
                        @include('admin.homepage.sections.partials.banner', ['i' => $i, 'banner' => $banner])
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header">Appearance &amp; countdown</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Countdown end (optional — adds a live timer)</label>
                        <input type="datetime-local" name="countdown" class="form-control"
                               value="{{ old('countdown', $section->settings['countdown'] ?? '') }}">
                    </div>
                    <div class="col-12">
                        <hr class="my-1">
                        @php
                            $cardEnabled = old('card_enabled', $section->settings['card_enabled'] ?? (($section->settings['bg_color'] ?? null) || ($section->settings['text_color'] ?? null)));
                        @endphp
                        <input type="hidden" name="card_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="sec-card-toggle"
                                   name="card_enabled" value="1" {{ $cardEnabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="sec-card-toggle">Wrap this section in a big coloured card</label>
                        </div>
                        <p class="small text-muted mb-2">Give the whole rail a card background — e.g. a red Flash&nbsp;Sale card like the big marketplaces. Choose one colour, a gradient, or two split colours.</p>
                        <div class="row g-3" id="sec-card-fields">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Card colour style</label>
                                <select name="bg_style" class="form-select form-select-sm" data-card-field>
                                    @foreach(['solid' => 'Single colour', 'gradient' => 'Gradient', 'split' => 'Two split colours'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('bg_style', $section->settings['bg_style'] ?? 'solid') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Background 1</label>
                                <input type="color" name="bg_color" id="sec-card-bg" class="form-control form-control-color" data-card-field
                                       value="{{ old('bg_color', $section->settings['bg_color'] ?? '#e03131') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Background 2</label>
                                <input type="color" name="bg_color_2" class="form-control form-control-color" data-card-field
                                       value="{{ old('bg_color_2', $section->settings['bg_color_2'] ?? '#f68b1e') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Text</label>
                                <input type="color" name="text_color" id="sec-card-fg" class="form-control form-control-color" data-card-field
                                       value="{{ old('text_color', $section->settings['text_color'] ?? '#ffffff') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Accent (icons, links)</label>
                                <input type="color" name="accent_color" class="form-control form-control-color" data-card-field
                                       value="{{ old('accent_color', $section->settings['accent_color'] ?? '#ffffff') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Edges</label>
                                <select name="card_edges" class="form-select form-select-sm" data-card-field>
                                    @foreach(['none' => 'Square', 'sm' => 'Slight', 'md' => 'Rounded', 'lg' => 'Big', 'pill' => 'Pill'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('card_edges', $section->settings['card_edges'] ?? 'lg') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Shape</label>
                                <select name="card_shape" class="form-select form-select-sm" data-card-field>
                                    @foreach(['rect' => 'Rectangle', 'rounded' => 'Rounded', 'pill' => 'Pill'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('card_shape', $section->settings['card_shape'] ?? 'rounded') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Presets">
                                    <button type="button" class="btn btn-outline-danger" data-bg="#e03131" data-bg2="#a61e1e" data-fg="#ffffff">Flash red</button>
                                    <button type="button" class="btn btn-outline-warning" data-bg="#f68b1e" data-bg2="#d9760c" data-fg="#ffffff">Juvo orange</button>
                                    <button type="button" class="btn btn-outline-dark" data-bg="#282828" data-bg2="#111111" data-fg="#ffffff">Dark</button>
                                    <button type="button" class="btn btn-outline-success" data-bg="#0f9d58" data-bg2="#087443" data-fg="#ffffff">Green</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header">Schedule</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Show from (optional)</label>
                        <input type="datetime-local" name="starts_at" class="form-control"
                               value="{{ old('starts_at', optional($section->starts_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hide after (optional)</label>
                        <input type="datetime-local" name="ends_at" class="form-control"
                               value="{{ old('ends_at', optional($section->ends_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div>
                            <input type="hidden" name="auto_delete" value="0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_delete" value="1" id="sec-auto-delete"
                                       {{ old('auto_delete', $section->auto_delete ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="sec-auto-delete">Delete automatically when the end time passes</label>
                            </div>
                            <p class="small text-muted mb-0">Leave end time empty to keep the section until you change or delete it.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3 mb-3">
            <div class="card-body">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" style="max-width:200px;">
                    <option value="published" {{ old('status', $section->status ?: 'published') === 'published' ? 'selected' : '' }}>Published</option>
                    <option value="draft" {{ old('status', $section->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                </select>
            </div>
        </div>

        <div class="mb-5">
            <button class="btn btn-primary">{{ $section->exists ? 'Save changes' : 'Create section' }}</button>
            <a href="{{ route('admin.homepage.sections.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <template id="card-template">
        <div class="border rounded p-3 mb-3 promo-card">
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small">Title</label><input name="cards[__INDEX__][title]" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">Subtitle</label><input name="cards[__INDEX__][subtitle]" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">Badge</label><input name="cards[__INDEX__][badge]" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">Button label</label><input name="cards[__INDEX__][cta_label]" class="form-control form-control-sm" value="Shop now"></div>
                <div class="col-md-4"><label class="form-label small">Destination URL</label><input name="cards[__INDEX__][url]" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label small">Size</label>
                    <select name="cards[__INDEX__][size]" class="form-select form-select-sm"><option value="sm">Small</option><option value="lg">Large</option></select>
                </div>
                <div class="col-md-2"><label class="form-label small">Theme</label>
                    <select name="cards[__INDEX__][theme]" class="form-select form-select-sm"><option value="light">Light</option><option value="orange">Orange</option><option value="dark">Dark</option></select>
                </div>
                <div class="col-md-6"><label class="form-label small">Image</label><input type="file" name="cards[__INDEX__][image]" accept="image/*" class="form-control form-control-sm"></div>
                <div class="col-md-6 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger remove-card">Remove card</button></div>
            </div>
        </div>
    </template>

    <template id="row-template">
        <div class="border rounded p-3 mb-3 product-row">
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small">Row title (optional)</label><input name="rows[__INDEX__][title]" class="form-control form-control-sm" placeholder="Trending now"></div>
                <div class="col-md-4"><label class="form-label small">Row subtitle (optional)</label><input name="rows[__INDEX__][subtitle]" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">Source</label>
                    <select name="rows[__INDEX__][source]" class="form-select form-select-sm">
                        <option value="featured">Featured products</option>
                        <option value="bestsellers">Best sellers</option>
                        <option value="trending">Trending now</option>
                        <option value="daily_deals">Daily deals (discounted)</option>
                        <option value="new_arrivals">New arrivals</option>
                        <option value="category">Specific category</option>
                        <option value="collection">Named collection</option>
                        <option value="sponsored">Sponsored products</option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label small">Category (source = Specific category)</label>
                    <select name="rows[__INDEX__][category_id]" class="form-select form-select-sm"><option value="">—</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->translation->name ?? $c->slug }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label small">Collection (source = Named collection)</label>
                    <select name="rows[__INDEX__][collection]" class="form-select form-select-sm"><option value="">—</option>
                        @foreach(($collections ?? []) as $col)<option value="{{ $col->slug }}">{{ $col->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label small">Row layout</label>
                    <select name="rows[__INDEX__][layout]" class="form-select form-select-sm"><option value="carousel">Carousel</option><option value="grid">Grid</option></select>
                </div>
                <div class="col-md-2"><label class="form-label small">Max products</label><input type="number" min="1" max="24" name="rows[__INDEX__][limit]" class="form-control form-control-sm" value="6"></div>
                <div class="col-12">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2"><label class="form-label small">Columns — desktop</label><input type="number" min="1" max="8" name="rows[__INDEX__][columns_desktop]" class="form-control form-control-sm" value="5"></div>
                        <div class="col-md-2"><label class="form-label small">Columns — tablet</label><input type="number" min="1" max="6" name="rows[__INDEX__][columns_tablet]" class="form-control form-control-sm" value="3"></div>
                        <div class="col-md-2"><label class="form-label small">Columns — mobile</label><input type="number" min="1" max="4" name="rows[__INDEX__][columns_mobile]" class="form-control form-control-sm" value="2"></div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="hidden" name="rows[__INDEX__][see_all]" value="0">
                                <input class="form-check-input" type="checkbox" name="rows[__INDEX__][see_all]" value="1">
                                <label class="form-check-label small">Show "See all" link</label>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove row</button></div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <template id="banner-template">
        @include('admin.homepage.sections.partials.banner', ['i' => '__INDEX__', 'banner' => []])
    </template>
@endsection

@section('js')
<script>
(function () {
    var typeSel = document.getElementById('sec-type');
    var layoutSel = document.getElementById('sec-layout');
    var sourceSel = document.querySelector('.sec-product-fields select[name="source"]');
    var productFields = document.querySelector('.sec-product-fields');
    var promoFields = document.querySelector('.sec-promo-fields');
    var rowsFields = document.querySelector('.sec-rows-fields');
    var collectionFields = document.querySelector('.sec-collection-fields');
    var mediaFields = document.querySelector('.sec-media-fields');
    var announcementFields = document.querySelector('.sec-announcement-fields');
    var bannersCard = document.getElementById('sec-banner-card');
    var collectionSourceSel = document.getElementById('sec-collection-source');
    var collectionNamed = document.querySelector('.sec-collection-named');
    var collectionCat = document.querySelector('.sec-collection-cat');
    var manualFields = document.querySelector('.sec-manual-fields');

    var cardToggle = document.getElementById('sec-card-toggle');
    var cardFields = document.getElementById('sec-card-fields');
    var cardBg = document.getElementById('sec-card-bg');
    var cardFg = document.getElementById('sec-card-fg');
    var cardBg2 = document.querySelector('[name="bg_color_2"]');
    var cardStyle = document.querySelector('[name="bg_style"]');

    function setEnabled(card, enabled) {
        if (!card) return;
        card.style.display = enabled ? '' : 'none';
        card.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = !enabled;
        });
    }

    function syncManual() {
        var manual = !!sourceSel && sourceSel.value === 'manual';
        if (manualFields) {
            manualFields.style.display = manual ? '' : 'none';
            manualFields.querySelectorAll('input, select, textarea').forEach(function (el) {
                el.disabled = !manual;
            });
        }
    }

    function syncCard() {
        var on = cardToggle.checked;
        if (cardFields) {
            cardFields.querySelectorAll('input, select, textarea').forEach(function (el) {
                el.disabled = !on;
            });
        }
    }

    function syncCollectionSource() {
        var cardOn = collectionFields && collectionFields.style.display !== 'none';
        var named = !collectionSourceSel || (collectionSourceSel.value || 'collections') === 'collections';

        setEnabled(collectionNamed, cardOn && named);
        setEnabled(collectionCat, cardOn && !named);
        document.querySelectorAll('.sec-collection-category-only').forEach(function (el) {
            var show = cardOn && !named;
            el.style.display = show ? '' : 'none';
            el.querySelectorAll('input, select, textarea').forEach(function (input) {
                input.disabled = !show;
            });
        });
    }

    function sync() {
        var type = typeSel.value;
        var promo = type === 'promo_grid';
        var collections = type === 'collection_carousel' || type === 'collection_grid';
        var image = type === 'image';
        var announcement = type === 'announcement';
        var mediaOnly = image || announcement || type === 'gallery' || type === 'slides';
        var rows = !promo && !collections && !mediaOnly && layoutSel.value === 'rows';

        setEnabled(promoFields, promo);
        setEnabled(rowsFields, rows);
        setEnabled(collectionFields, collections);
        setEnabled(mediaFields, image);
        setEnabled(announcementFields, announcement);
        setEnabled(productFields, !promo && !collections && !mediaOnly && !rows);
        if (bannersCard) {
            bannersCard.style.display = (image || announcement) ? 'none' : '';
        }
        syncManual();
        syncCard();
        syncCollectionSource();
    }

    typeSel.addEventListener('change', sync);
    layoutSel.addEventListener('change', sync);
    if (sourceSel) sourceSel.addEventListener('change', syncManual);
    if (collectionSourceSel) collectionSourceSel.addEventListener('change', syncCollectionSource);
    cardToggle.addEventListener('change', syncCard);

    document.querySelectorAll('[data-bg][data-fg]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            cardToggle.checked = true;
            syncCard();
            if (cardStyle) cardStyle.value = 'solid';
            cardBg.value = btn.dataset.bg;
            cardFg.value = btn.dataset.fg;
            if (cardBg2 && btn.dataset.bg2) cardBg2.value = btn.dataset.bg2;
        });
    });

    sync();

    var wrap = document.getElementById('cards-wrap');
    var counter = wrap.querySelectorAll('.promo-card').length;

    document.getElementById('add-card').addEventListener('click', function () {
        var html = document.getElementById('card-template').innerHTML.replace(/__INDEX__/g, counter++);
        wrap.insertAdjacentHTML('beforeend', html);
    });

    wrap.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-card')) {
            e.target.closest('.promo-card').remove();
        }
    });

    var rowsWrap = document.getElementById('rows-wrap');
    var rowCounter = rowsWrap.querySelectorAll('.product-row').length;

    document.getElementById('add-row').addEventListener('click', function () {
        var html = document.getElementById('row-template').innerHTML.replace(/__INDEX__/g, rowCounter++);
        rowsWrap.insertAdjacentHTML('beforeend', html);
    });

    rowsWrap.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row')) {
            e.target.closest('.product-row').remove();
        }
    });

    var bannersWrap = document.getElementById('banners-wrap');
    var addBanner = document.getElementById('add-banner');
    var bannerCounter = bannersWrap ? bannersWrap.querySelectorAll('.sec-banner').length : 0;

    if (addBanner && bannersWrap) {
        addBanner.addEventListener('click', function () {
            var html = document.getElementById('banner-template').innerHTML.replace(/__INDEX__/g, bannerCounter++);
            bannersWrap.insertAdjacentHTML('beforeend', html);
        });

        bannersWrap.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-banner')) {
                e.target.closest('.sec-banner').remove();
            }
        });
    }
})();
</script>
@endsection
