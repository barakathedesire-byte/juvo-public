{{--
    Recursive category navigation list.
    Expects: $items (collection of categories), $level (int, optional)
    Each item may eager-load `children` and `translation`.
--}}
@php
    $level = $level ?? 0;
    $items = $items ?? collect();
@endphp
@foreach ($items as $cat)
    @php
        $children = $cat->relationLoaded('children') ? $cat->children : collect();
        $hasChildren = $children->count() > 0;
    @endphp
    <li class="jv-cat-item jv-cat-lv{{ $level }}">
        @if ($hasChildren)
            <a class="jv-cat-parent jv-cat-toggle" data-bs-toggle="collapse" href="#jvcat-{{ $cat->id }}" role="button" aria-expanded="false">
                <span>{{ optional($cat->translation)->name ?? $cat->slug }}</span>
                <i class="fa-solid fa-angle-down"></i>
            </a>
            <div class="collapse" id="jvcat-{{ $cat->id }}">
                <a class="jv-cat-child jv-cat-all" href="{{ route('category.show', $cat->slug) }}">
                    All {{ optional($cat->translation)->name ?? '' }}
                </a>
                <ul class="jv-cat-sub">
                    @include('themes.xylo.partials.category-nav', ['items' => $children, 'level' => $level + 1])
                </ul>
            </div>
        @else
            <a class="jv-cat-parent" href="{{ route('category.show', $cat->slug) }}">
                {{ optional($cat->translation)->name ?? $cat->slug }}
            </a>
        @endif
    </li>
@endforeach
