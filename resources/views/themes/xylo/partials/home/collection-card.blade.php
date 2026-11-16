{{--
    A single collection tile for a "Collections" homepage section.
    Expects: $collection = ['name' => ..., 'slug' => ..., 'url' => ..., 'count' => ...]
--}}
@php
    $collectionSize = $collection['size'] ?? ($collectionSize ?? 'md');
    $collectionShape = $collection['shape'] ?? ($collectionShape ?? 'rounded');
    $collectionClasses = 'jv-collection-card jv-collection-card--'.$collectionSize.' jv-collection-card--'.$collectionShape;
    $collectionTextStyles = [];
    if (! empty($collection['text_color'])) {
        $collectionTextStyles[] = 'color:'.e($collection['text_color']);
    }
    $collectionShapeStyles = [];
    if (! empty($collection['bg_color'])) {
        $collectionShapeStyles[] = 'background-color:'.e($collection['bg_color']);
    }
    $collectionImage = $collection['image'] ?? null;
    if ($collectionImage) {
        if (\Illuminate\Support\Str::startsWith($collectionImage, ['http://', 'https://'])) {
            // keep absolute URL
        } elseif (\Illuminate\Support\Str::startsWith($collectionImage, ['/storage/', 'storage/'])) {
            $collectionImage = asset(ltrim($collectionImage, '/'));
        } else {
            $collectionImage = \Illuminate\Support\Facades\Storage::url($collectionImage);
        }
    }
@endphp
<a href="{{ $collection['url'] }}" class="{{ $collectionClasses }}" @if($collectionTextStyles) style="{{ implode(';', $collectionTextStyles) }}" @endif>
    <span class="jv-collection-card-shape" @if($collectionShapeStyles) style="{{ implode(';', $collectionShapeStyles) }}" @endif>
        @if($collectionImage)
            <img src="{{ $collectionImage }}" alt="{{ $collection['name'] }}" loading="lazy">
        @else
            <span class="jv-collection-card-fallback"><i class="fa-solid fa-layer-group"></i></span>
        @endif
    </span>
    <span class="jv-collection-card-body">
        <span class="jv-collection-card-name">{{ $collection['name'] }}</span>
        <span class="jv-collection-card-count">
            {{ (int) ($collection['count'] ?? 0) }} {{ \Illuminate\Support\Str::plural('item', (int) ($collection['count'] ?? 0)) }}
        </span>
        <span class="jv-collection-card-cta">Shop collection <i class="fa-solid fa-arrow-right"></i></span>
    </span>
</a>
