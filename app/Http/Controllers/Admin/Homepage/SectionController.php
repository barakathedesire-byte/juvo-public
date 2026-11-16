<?php

namespace App\Http\Controllers\Admin\Homepage;

use App\Http\Controllers\Admin\Homepage\Concerns\HandlesHomepageMedia;
use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    use HandlesHomepageMedia;

    protected array $sources = [
        'featured' => 'Featured products',
        'bestsellers' => 'Best sellers',
        'trending' => 'Trending now',
        'daily_deals' => 'Daily deals (discounted)',
        'new_arrivals' => 'New arrivals',
        'category' => 'Specific category',
        'collection' => 'Named collection',
        'manual' => 'Hand-picked products',
        'sponsored' => 'Sponsored products',
    ];

    public function index()
    {
        HomepageSection::purgeExpired();

        $sections = HomepageSection::ordered()->get();

        return view('admin.homepage.sections.index', compact('sections'));
    }

    public function create()
    {
        return view('admin.homepage.sections.form', [
            'section' => new HomepageSection(['type' => 'product_carousel', 'source' => 'featured', 'layout' => 'carousel', 'limit' => 10, 'status' => 'published']),
            'sources' => $this->sources,
            'categories' => $this->categories(),
            'collections' => $this->collections(),
            'products' => $this->products(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) HomepageSection::max('sort_order') + 1;

        HomepageSection::create($data);

        return redirect()->route('admin.homepage.sections.index')->with('success', 'Section created.');
    }

    public function edit(HomepageSection $section)
    {
        return view('admin.homepage.sections.form', [
            'section' => $section,
            'sources' => $this->sources,
            'categories' => $this->categories(),
            'collections' => $this->collections(),
            'products' => $this->products(),
        ]);
    }

    public function update(Request $request, HomepageSection $section)
    {
        $section->update($this->validated($request, $section));

        return redirect()->route('admin.homepage.sections.index')->with('success', 'Section updated.');
    }

    public function destroy(HomepageSection $section)
    {
        $section->purgeImages();
        $section->delete();

        return back()->with('success', 'Section deleted.');
    }

    public function toggle(HomepageSection $section)
    {
        $section->update(['status' => $section->status === 'published' ? 'draft' : 'published']);

        return back()->with('success', 'Section visibility updated.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:homepage_sections,id',
        ]);

        foreach ($data['order'] as $position => $id) {
            HomepageSection::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['message' => 'Order saved.']);
    }

    protected function categories()
    {
        return \App\Models\Category::with('translation')
            ->orderByRaw('COALESCE(parent_category_id, id)')
            ->orderBy('parent_category_id')
            ->orderBy('id')
            ->get();
    }

    protected function collections()
    {
        return \App\Models\Collection::marketplace()->ordered()->get();
    }

    protected function products()
    {
        return \App\Models\Product::visible()
            ->with('translation')
            ->orderByDesc('id')
            ->limit(800)
            ->get();
    }

    protected function validated(Request $request, ?HomepageSection $section = null): array
    {
        $data = $request->validate([
            'type' => 'required|in:product_carousel,product_grid,collection_carousel,collection_grid,promo_grid,image,gallery,slides,announcement',
            'title' => 'nullable|string|max:120',
            'subtitle' => 'nullable|string|max:190',
            'icon' => 'nullable|string|max:60',
            'source' => 'nullable|in:featured,bestsellers,trending,daily_deals,new_arrivals,category,collection,manual,sponsored,promo',
            'category_id' => 'nullable|integer|exists:categories,id',
            'collection' => 'nullable|string|max:120',
            'collections' => 'nullable|array',
            'collections.*' => 'string|max:120',
            'collection_source' => 'nullable|in:collections,category,subcategory',
            'collection_category_id' => 'nullable|integer|exists:categories,id',
            'collection_bg_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'collection_text_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'collection_size' => 'nullable|in:auto,sm,md,lg',
            'collection_shape' => 'nullable|in:auto,circle,rounded,square',
            'collection_images' => 'nullable|array',
            'collection_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'existing_collection_images' => 'nullable|array',
            'existing_collection_images.*' => 'nullable|string|max:500',
            'collection_names' => 'nullable|array',
            'collection_names.*' => 'nullable|string|max:120',
            'products' => 'nullable|array',
            'products.*' => 'integer|exists:products,id',
            'countdown' => 'nullable|date',
            'bg_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'text_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'card_enabled' => 'nullable|boolean',
            'bg_style' => 'nullable|in:solid,gradient,split',
            'bg_color_2' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'card_edges' => 'nullable|in:none,sm,md,lg,pill',
            'card_shape' => 'nullable|in:rect,rounded,pill',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'auto_delete' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'image_url' => 'nullable|string|max:500',
            'existing_image' => 'nullable|string|max:500',
            'image_link' => 'nullable|string|max:255',
            'image_caption' => 'nullable|string|max:160',
            'image_alt' => 'nullable|string|max:160',
            'image_rounded' => 'nullable|boolean',
            'announcement_body' => 'nullable|string|max:1200',
            'announcement_tone' => 'nullable|in:info,success,warning,promo',
            'announcement_cta_label' => 'nullable|string|max:60',
            'announcement_cta_url' => 'nullable|string|max:255',
            'layout' => 'nullable|in:carousel,grid,rows',
            'limit' => 'nullable|integer|min:1|max:24',
            'status' => 'required|in:draft,published',
            'rows' => 'nullable|array',
            'rows.*.title' => 'nullable|string|max:120',
            'rows.*.subtitle' => 'nullable|string|max:190',
            'rows.*.source' => 'nullable|in:featured,bestsellers,trending,daily_deals,new_arrivals,category,collection,manual,sponsored',
            'rows.*.category_id' => 'nullable|integer|exists:categories,id',
            'rows.*.collection' => 'nullable|string|max:120',
            'rows.*.product_ids' => 'nullable|array',
            'rows.*.product_ids.*' => 'integer|exists:products,id',
            'rows.*.layout' => 'nullable|in:carousel,grid',
            'rows.*.limit' => 'nullable|integer|min:1|max:24',
            'rows.*.columns_desktop' => 'nullable|integer|min:1|max:8',
            'rows.*.columns_tablet' => 'nullable|integer|min:1|max:6',
            'rows.*.columns_mobile' => 'nullable|integer|min:1|max:4',
            'rows.*.see_all' => 'nullable|boolean',
            'cards' => 'nullable|array',
            'cards.*.title' => 'nullable|string|max:120',
            'cards.*.subtitle' => 'nullable|string|max:190',
            'cards.*.cta_label' => 'nullable|string|max:60',
            'cards.*.url' => 'nullable|string|max:255',
            'cards.*.size' => 'nullable|in:lg,sm',
            'cards.*.badge' => 'nullable|string|max:60',
            'cards.*.theme' => 'nullable|in:orange,dark,light',
            'cards.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'banners' => 'nullable|array',
            'banners.*.title' => 'nullable|string|max:120',
            'banners.*.subtitle' => 'nullable|string|max:190',
            'banners.*.url' => 'nullable|string|max:255',
            'banners.*.size' => 'nullable|in:sm,md,lg,wide,tall,full',
            'banners.*.edges' => 'nullable|in:none,sm,md,lg,pill',
            'banners.*.shape' => 'nullable|in:rect,rounded,pill,circle,hex',
            'banners.*.effect' => 'nullable|in:none,shadow,glow,overlay,zoom,parallax',
            'banners.*.bg_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'banners.*.text_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'banners.*.gradient_from' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'banners.*.gradient_to' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'banners.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'banners.*.image_url' => 'nullable|string|max:500',
            'banners.*.existing_image' => 'nullable|string|max:500',
        ]);

        $isMedia = in_array($data['type'], ['image', 'gallery', 'slides', 'announcement'], true);

        $data['source'] = $isMedia ? 'media' : ($data['source'] ?? 'featured');
        $data['layout'] = $isMedia ? 'grid' : ($data['layout'] ?? 'carousel');
        $data['limit'] = (int) ($data['limit'] ?? 10);

        $settings = [];

        if ($data['type'] === 'image') {
            $settings = $this->buildImageSettings($request, $section);
        } elseif ($data['type'] === 'announcement') {
            $settings = $this->buildAnnouncementSettings($request);
        } elseif (in_array($data['type'], ['gallery', 'slides'], true)) {
            $settings = [];
        } elseif ($data['type'] === 'promo_grid') {
            $settings = ['cards' => $this->buildCards($request, $section)];
            $data['source'] = 'promo';
        } elseif (in_array($data['type'], ['collection_carousel', 'collection_grid'], true)) {
            $collectionSource = $data['collection_source'] ?? 'collections';

            if (in_array($collectionSource, ['category', 'subcategory'], true)) {
                $categoryId = (int) ($data['collection_category_id'] ?? 0);

                if ($categoryId <= 0 || ! \App\Models\Category::whereKey($categoryId)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'collection_category_id' => 'Choose a category for this collection section.',
                    ]);
                }

                $settings = [
                    'collection_source' => $collectionSource,
                    'collection_category_id' => $categoryId,
                ];
            } else {
                $slugs = collect($request->input('collections', []))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($slugs)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'collections' => 'Choose at least one collection for this section.',
                    ]);
                }

                $settings = [
                    'collection_source' => 'collections',
                    'collections' => $slugs,
                ];

                // Named collections are an explicit selection: show every one of
                // them instead of silently cutting the list off at "Max cards".
                $data['limit'] = count($slugs);
            }

            if ($request->filled('collection_bg_color')) {
                $settings['collection_bg_color'] = $request->input('collection_bg_color');
            }
            if ($request->filled('collection_text_color')) {
                $settings['collection_text_color'] = $request->input('collection_text_color');
            }
            if ($request->filled('collection_size') && $request->input('collection_size') !== 'auto') {
                $settings['collection_size'] = $request->input('collection_size');
            }
            if ($request->filled('collection_shape') && $request->input('collection_shape') !== 'auto') {
                $settings['collection_shape'] = $request->input('collection_shape');
            }

            $collectionImages = $this->buildCollectionImages($request, $section);
            if ($collectionImages) {
                $settings['collection_images'] = $collectionImages;
            }

            $collectionNames = collect($request->input('collection_names', []))
                ->map(fn ($value) => is_string($value) ? trim($value) : $value)
                ->filter(fn ($value) => filled($value))
                ->all();

            if ($collectionNames) {
                $settings['collection_names'] = $collectionNames;
            }
        } else {
            if ($data['layout'] === 'rows') {
                $settings['rows'] = $this->buildRows($request);
            } else {
                if ($data['source'] === 'category' && empty($data['category_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'category_id' => 'Select a category when the source is "Specific category".',
                    ]);
                }

                if ($data['source'] === 'collection') {
                    $slug = $request->input('collection');
                    $exists = \App\Models\Collection::query()
                        ->marketplace()
                        ->where('slug', $slug)
                        ->exists();

                    if (! $slug || ! $exists) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'collection' => 'Select a marketplace collection when the source is "Named collection".',
                        ]);
                    }

                    $settings['collection'] = $slug;
                }

                if ($data['source'] === 'manual') {
                    $ids = collect($request->input('products', []))
                        ->map(fn ($id) => (int) $id)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if (empty($ids)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'products' => 'Choose at least one product for a hand-picked section.',
                        ]);
                    }

                    $settings['product_ids'] = $ids;
                }
            }
        }

        if ($request->filled('countdown')) {
            $settings['countdown'] = $request->input('countdown');
        }

        $cardEnabled = $request->boolean('card_enabled');

        $settings['card_enabled'] = $cardEnabled;

        if ($cardEnabled) {
            $settings['bg_color'] = $request->input('bg_color') ?: null;
            $settings['bg_color_2'] = $request->input('bg_color_2') ?: null;
            $settings['bg_style'] = $request->input('bg_style') ?: 'solid';
            $settings['text_color'] = $request->input('text_color') ?: null;
            $settings['accent_color'] = $request->input('accent_color') ?: null;
            $settings['card_edges'] = $request->input('card_edges') ?: 'lg';
            $settings['card_shape'] = $request->input('card_shape') ?: 'rounded';
        }

        foreach (['starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                $data[$field] = null;
            }
        }

        $data['auto_delete'] = $request->boolean('auto_delete') && ! empty($data['ends_at']);

        $data['settings'] = $settings ?: null;

        $banners = $this->buildBanners($request, $section);
        if ($banners) {
            $data['settings'] = array_merge($data['settings'] ?? [], ['banners' => $banners]);
        }

        unset(
            $data['cards'], $data['rows'], $data['collection'], $data['collections'],
            $data['collection_source'], $data['collection_category_id'],
            $data['products'], $data['countdown'], $data['bg_color'], $data['text_color'],
            $data['banners'], $data['collection_bg_color'], $data['collection_text_color'],
            $data['collection_size'], $data['collection_shape'], $data['collection_images'],
            $data['existing_collection_images'], $data['collection_names'],
            $data['card_enabled'], $data['bg_style'], $data['bg_color_2'], $data['accent_color'],
            $data['card_edges'], $data['card_shape'], $data['image'], $data['image_url'],
            $data['existing_image'], $data['image_link'], $data['image_caption'],
            $data['image_alt'], $data['image_rounded'], $data['announcement_body'],
            $data['announcement_tone'], $data['announcement_cta_label'], $data['announcement_cta_url']
        );

        return $data;
    }

    protected function buildImageSettings(Request $request, ?HomepageSection $section): array
    {
        $existing = $section->settings['image'] ?? null;
        $image = $request->input('existing_image') ?: $existing;

        if ($request->hasFile('image')) {
            $this->deleteImage($existing);
            $image = $this->storeImage($request->file('image'), 'sections');
        } elseif ($request->filled('image_url')) {
            if ($image !== $request->input('image_url')) {
                $this->deleteImage($existing);
            }
            $image = $request->input('image_url');
        }

        $this->assertDestination($request->input('image_link'), 'image_link');

        return [
            'image' => $image,
            'link' => $request->input('image_link') ?: null,
            'caption' => $request->input('image_caption') ?: null,
            'alt' => $request->input('image_alt') ?: null,
            'rounded' => $request->boolean('image_rounded'),
        ];
    }

    protected function buildAnnouncementSettings(Request $request): array
    {
        $this->assertDestination($request->input('announcement_cta_url'), 'announcement_cta_url');

        return [
            'body' => $request->input('announcement_body') ?: null,
            'tone' => $request->input('announcement_tone') ?: 'info',
            'cta_label' => $request->input('announcement_cta_label') ?: null,
            'cta_url' => $request->input('announcement_cta_url') ?: null,
        ];
    }

    protected function buildBanners(Request $request, ?HomepageSection $section): array
    {
        $existing = $section->settings['banners'] ?? [];
        $banners = [];

        foreach ($request->input('banners', []) as $i => $b) {
            $hasUpload = $request->hasFile("banners.$i.image");
            $url = $b['url'] ?? null;

            if (
                empty($b['title']) && empty($b['subtitle']) && empty($b['image_url'])
                && ! $hasUpload && empty($b['existing_image']) && empty($url)
            ) {
                continue;
            }

            $this->assertDestination($url, "banners.$i.url");

            $image = $existing[$i]['image'] ?? ($b['existing_image'] ?? null);

            if ($hasUpload) {
                $this->deleteImage($image);
                $image = $this->storeImage($request->file("banners.$i.image"), 'banners');
            } elseif (! empty($b['image_url'])) {
                $image = $b['image_url'];
            }

            $banners[] = [
                'title' => $b['title'] ?? null,
                'subtitle' => $b['subtitle'] ?? null,
                'url' => $url ?: null,
                'size' => $b['size'] ?? 'md',
                'edges' => $b['edges'] ?? 'md',
                'shape' => $b['shape'] ?? 'rounded',
                'effect' => $b['effect'] ?? 'none',
                'bg_color' => $b['bg_color'] ?? null,
                'text_color' => $b['text_color'] ?? null,
                'gradient_from' => $b['gradient_from'] ?? null,
                'gradient_to' => $b['gradient_to'] ?? null,
                'image' => $image,
            ];
        }

        return $banners;
    }

    protected function buildCollectionImages(Request $request, ?HomepageSection $section): array
    {
        $existing = $section->settings['collection_images'] ?? [];
        $images = $existing;

        foreach ($request->input('existing_collection_images', []) as $slug => $path) {
            if ($path) {
                $images[$slug] = $path;
            }
        }

        foreach (array_keys($request->file('collection_images', [])) as $slug) {
            if ($request->hasFile("collection_images.$slug")) {
                if (! empty($images[$slug])) {
                    $this->deleteImage($images[$slug]);
                }
                $images[$slug] = $this->storeImage($request->file("collection_images.$slug"), 'collection-cards');
            }
        }

        return $images;
    }

    protected function buildRows(Request $request): array
    {
        $rows = [];

        foreach ($request->input('rows', []) as $row) {
            $rows[] = [
                'title' => $row['title'] ?? null,
                'subtitle' => $row['subtitle'] ?? null,
                'source' => $row['source'] ?? 'featured',
                'category_id' => ! empty($row['category_id']) ? (int) $row['category_id'] : null,
                'collection' => $row['collection'] ?? null,
                'product_ids' => collect($row['product_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all(),
                'layout' => ($row['layout'] ?? 'carousel') === 'grid' ? 'grid' : 'carousel',
                'limit' => (int) ($row['limit'] ?? 6),
                'columns_desktop' => (int) ($row['columns_desktop'] ?? 5),
                'columns_tablet' => (int) ($row['columns_tablet'] ?? 3),
                'columns_mobile' => (int) ($row['columns_mobile'] ?? 2),
                'see_all' => filter_var($row['see_all'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return $rows;
    }

    protected function buildCards(Request $request, ?HomepageSection $section): array
    {
        $existing = $section->settings['cards'] ?? [];
        $cards = [];
        $input = $request->input('cards', []);

        foreach ($input as $i => $card) {
            $url = $card['url'] ?? null;
            $this->assertDestination($url, "cards.$i.url");

            $image = $existing[$i]['image'] ?? null;
            if ($request->hasFile("cards.$i.image")) {
                $this->deleteImage($image);
                $image = $this->storeImage($request->file("cards.$i.image"), 'promos');
            }

            $cards[] = [
                'title' => $card['title'] ?? '',
                'subtitle' => $card['subtitle'] ?? null,
                'cta_label' => $card['cta_label'] ?? 'Shop now',
                'url' => $url ?: '/products',
                'size' => ($card['size'] ?? 'sm') === 'lg' ? 'lg' : 'sm',
                'badge' => $card['badge'] ?? null,
                'theme' => $card['theme'] ?? 'light',
                'image' => $image,
            ];
        }

        return $cards;
    }
}
