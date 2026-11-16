@php
    $sources = $sources ?? [];
    $categories = $categories ?? collect();
    $collections = $collections ?? collect();
    $columns = [
        'desktop' => (int) ($row['columns_desktop'] ?? 5),
        'tablet' => (int) ($row['columns_tablet'] ?? 3),
        'mobile' => (int) ($row['columns_mobile'] ?? 2),
    ];
@endphp
<div class="border rounded p-3 mb-3 product-row" data-index="{{ $i }}">
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label small">Row title (optional)</label>
            <input name="rows[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $row['title'] ?? '' }}" placeholder="Trending now">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Row subtitle (optional)</label>
            <input name="rows[{{ $i }}][subtitle]" class="form-control form-control-sm" value="{{ $row['subtitle'] ?? '' }}">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Source</label>
            <select name="rows[{{ $i }}][source]" class="form-select form-select-sm">
                @foreach($sources as $val => $label)
                    <option value="{{ $val }}" {{ ($row['source'] ?? 'featured') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label small">Category (source = Specific category)</label>
            <select name="rows[{{ $i }}][category_id]" class="form-select form-select-sm">
                @include('admin.partials.category-options', ['categories' => $categories, 'selected' => $row['category_id'] ?? null, 'placeholder' => '—'])
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Collection (source = Named collection)</label>
            <select name="rows[{{ $i }}][collection]" class="form-select form-select-sm">
                <option value="">—</option>
                @foreach($collections as $col)
                    <option value="{{ $col->slug }}" {{ ($row['collection'] ?? '') === $col->slug ? 'selected' : '' }}>{{ $col->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Row layout</label>
            <select name="rows[{{ $i }}][layout]" class="form-select form-select-sm">
                <option value="carousel" {{ ($row['layout'] ?? 'carousel') === 'carousel' ? 'selected' : '' }}>Carousel</option>
                <option value="grid" {{ ($row['layout'] ?? '') === 'grid' ? 'selected' : '' }}>Grid</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Max products</label>
            <input type="number" min="1" max="24" name="rows[{{ $i }}][limit]" class="form-control form-control-sm" value="{{ $row['limit'] ?? 6 }}">
        </div>

        <div class="col-12">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Columns — desktop</label>
                    <input type="number" min="1" max="8" name="rows[{{ $i }}][columns_desktop]" class="form-control form-control-sm" value="{{ $columns['desktop'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Columns — tablet</label>
                    <input type="number" min="1" max="6" name="rows[{{ $i }}][columns_tablet]" class="form-control form-control-sm" value="{{ $columns['tablet'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Columns — mobile</label>
                    <input type="number" min="1" max="4" name="rows[{{ $i }}][columns_mobile]" class="form-control form-control-sm" value="{{ $columns['mobile'] }}">
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input type="hidden" name="rows[{{ $i }}][see_all]" value="0">
                        <input class="form-check-input" type="checkbox" name="rows[{{ $i }}][see_all]" value="1"
                               id="row-see-all-{{ $i }}" {{ !empty($row['see_all']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="row-see-all-{{ $i }}">Show "See all" link</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove row</button>
                </div>
            </div>
        </div>
    </div>
</div>
