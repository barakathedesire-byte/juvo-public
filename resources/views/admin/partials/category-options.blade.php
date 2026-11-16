{{--
    Grouped category <option> list with subcategories.
    Expects: $categories (flat collection incl. children), optional $selected (id),
    optional $placeholder (empty first option label).
--}}
@php
    $selected = $selected ?? old('category_id');
    $all = collect($categories);
    $byParent = $all->groupBy(fn ($c) => (string) ($c->parent_category_id ?? 0));
    $roots = $all->filter(fn ($c) => ! $c->parent_category_id);
    $name = fn ($c) => $c->translation->name ?? ($c->name ?? '—');
@endphp
@isset($placeholder)
    <option value="">{{ $placeholder }}</option>
@endisset
@forelse($roots as $root)
    <optgroup label="{{ $name($root) }}">
        <option value="{{ $root->id }}" {{ (string) $selected === (string) $root->id ? 'selected' : '' }}>{{ $name($root) }}</option>
        @foreach($byParent[(string) $root->id] ?? [] as $child)
            <option value="{{ $child->id }}" {{ (string) $selected === (string) $child->id ? 'selected' : '' }}>&mdash; {{ $name($child) }}</option>
            @foreach($byParent[(string) $child->id] ?? [] as $grand)
                <option value="{{ $grand->id }}" {{ (string) $selected === (string) $grand->id ? 'selected' : '' }}>&nbsp;&nbsp;&mdash; {{ $name($grand) }}</option>
            @endforeach
        @endforeach
    </optgroup>
@empty
@endforelse
@foreach($all->filter(fn ($c) => $c->parent_category_id && ! $roots->contains('id', $c->parent_category_id)) as $orphan)
    <option value="{{ $orphan->id }}" {{ (string) $selected === (string) $orphan->id ? 'selected' : '' }}>{{ $name($orphan) }}</option>
@endforeach
