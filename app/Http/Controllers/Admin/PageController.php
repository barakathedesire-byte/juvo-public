<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Services\Pages\PageBlocks;
use App\Services\Pages\PageRegistry;
use App\Services\Pages\PageRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class PageController extends Controller
{
    public function __construct(protected PageRegistry $registry) {}

    /* ------------------------------------------------------------------ */
    /* Dashboard */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $this->registry->ensureSystemPages();

        $counts = [
            'all' => Page::count(),
            'published' => Page::where('status', 1)->count(),
            'draft' => Page::where('status', 0)->count(),
            'system' => Page::where('is_system', true)->count(),
            'custom' => Page::where('is_system', false)->count(),
        ];

        $virtual = PageRegistry::virtualRoutes();
        $types = Page::TYPES;

        return view('admin.pages.index', compact('counts', 'virtual', 'types'));
    }

    public function data(Request $request)
    {
        $this->registry->ensureSystemPages();

        $query = Page::with('translations')->select('pages.*');

        if ($type = $request->input('filter_type')) {
            $query->where('page_type', $type);
        }
        if ($request->input('filter_status') === 'published') {
            $query->where('status', 1);
        } elseif ($request->input('filter_status') === 'draft') {
            $query->where('status', 0);
        }

        return DataTables::of($query)
            ->addColumn('title', fn (Page $p) => $p->title())
            ->addColumn('url', function (Page $p) {
                if ($p->isSystem()) {
                    $def = PageRegistry::systemDefinitions()[$p->system_key] ?? null;
                    $url = $def['url'] ?? null;

                    return $url
                        ? '<a href="'.url($url).'" target="_blank" class="text-decoration-none">'.e($url).'</a>'
                        : '<span class="text-muted">—</span>';
                }

                return '<a href="'.route('page.show', $p->slug).'" target="_blank" class="text-decoration-none">/page/'.e($p->slug).'</a>';
            })
            ->editColumn('page_type', fn (Page $p) => '<span class="badge bg-light text-dark border">'.e(Page::TYPES[$p->page_type] ?? $p->page_type).'</span>')
            ->editColumn('status', fn (Page $p) => $p->status
                ? '<span class="badge bg-success">Published</span>'
                : '<span class="badge bg-secondary">Draft</span>')
            ->editColumn('updated_at', fn (Page $p) => $p->updated_at?->format('Y-m-d H:i'))
            ->addColumn('action', function (Page $p) {
                $editor = $p->template === 'content' && ! $p->isSystem()
                    ? route('admin.pages.edit', $p->id)
                    : route('admin.pages.editor', $p->id);
                $preview = route('admin.pages.preview', $p->id);
                $html = '<div class="d-flex gap-1 justify-content-end">';
                $html .= '<a href="'.$editor.'" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>';
                $html .= '<a href="'.$preview.'" target="_blank" class="btn btn-sm btn-outline-secondary" title="Preview"><i class="bi bi-eye"></i></a>';
                $html .= '<button type="button" class="btn btn-sm btn-outline-dark" onclick="duplicatePage('.$p->id.')" title="Duplicate"><i class="bi bi-files"></i></button>';
                if (! $p->isSystem()) {
                    $html .= '<button type="button" class="btn btn-sm btn-outline-danger" onclick="deletePage('.$p->id.')" title="Delete"><i class="bi bi-trash-fill"></i></button>';
                }
                $html .= '</div>';

                return $html;
            })
            ->rawColumns(['url', 'page_type', 'status', 'action'])
            ->make(true);
    }

    /** Virtual (non-stored) dynamic pages for the dashboard. */
    public function virtualData(Request $request)
    {
        $rows = collect(PageRegistry::virtualRoutes())->map(function ($r) {
            return [
                'name' => $r['name'],
                'url' => $r['url'],
                'type' => 'Dynamic template',
                'note' => $r['note'],
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }

    /* ------------------------------------------------------------------ */
    /* Create / store */
    /* ------------------------------------------------------------------ */

    public function create()
    {
        $activeLanguages = Language::where('active', true)->get();
        $pages = Page::where('is_system', false)->orderBy('slug')->get();
        $types = collect(Page::TYPES)->except('system')->all();
        $templates = Page::TEMPLATES;

        return view('admin.pages.create', compact('activeLanguages', 'pages', 'types', 'templates'));
    }

    public function store(Request $request)
    {
        $default = config('app.locale');

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:180',
            'page_type' => 'required|in:'.implode(',', array_keys(collect(Page::TYPES)->except('system')->all())),
            'template' => 'required|in:'.implode(',', array_keys(Page::TEMPLATES)),
            'parent_id' => 'nullable|exists:pages,id',
            'status' => 'nullable|boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
        ]);

        $slug = $this->uniqueSlug(($data['slug'] ?? null) ?: $data['title']);

        $page = DB::transaction(function () use ($data, $slug, $request) {
            $page = Page::create([
                'slug' => $slug,
                'status' => (int) $request->input('status', 1),
                'page_type' => $data['page_type'],
                'template' => $data['template'],
                'parent_id' => $data['parent_id'] ?? null,
                'is_system' => false,
                'seo_title' => $data['seo_title'] ?? null,
                'seo_description' => $data['seo_description'] ?? null,
                'blocks' => [],
            ]);

            PageTranslation::create([
                'page_id' => $page->id,
                'language_code' => app()->getLocale(),
                'title' => $data['title'],
                'content' => $request->input('content'),
            ]);

            $this->snapshot($page, 'Created', $request->user()?->id);

            return $page;
        });

        if ($data['template'] === 'content') {
            return redirect()->route('admin.pages.edit', $page->id)->with('success', 'Page created. Add its content below.');
        }

        return redirect()->route('admin.pages.editor', $page->id)->with('success', 'Page created. Start building it below.');
    }

    /* ------------------------------------------------------------------ */
    /* Content-template edit (existing CMS behaviour, preserved) */
    /* ------------------------------------------------------------------ */

    public function edit(Page $page)
    {
        $activeLanguages = Language::where('active', true)->get();

        return view('admin.pages.edit', compact('page', 'activeLanguages'));
    }

    public function update(Request $request, Page $page)
    {
        $rules = ['translations' => 'required|array'];
        foreach ($request->input('translations', []) as $lang => $t) {
            $rules["translations.$lang.title"] = 'required|string|max:255';
            $rules["translations.$lang.content"] = 'nullable|string';
            $rules["translations.$lang.image"] = 'nullable|image|max:2048';
        }
        $request->validate($rules);

        $page->update(['status' => $request->input('status', $page->status)]);

        foreach ($request->input('translations', []) as $lang => $data) {
            $translation = PageTranslation::firstOrNew([
                'page_id' => $page->id,
                'language_code' => $lang,
            ]);

            $imagePath = $translation->image_url;

            if (($data['image'] ?? null) instanceof \Illuminate\Http\UploadedFile) {
                if ($imagePath && ! Str::startsWith($imagePath, ['http://', 'https://'])) {
                    Storage::disk('public')->delete($imagePath);
                }
                $imagePath = $data['image']->store('pages', 'public');
            }

            $translation->fill([
                'title' => $data['title'],
                'content' => $data['content'] ?? $translation->content,
                'image_url' => $imagePath,
            ])->save();
        }

        return redirect()->route('admin.pages.index')->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page)
    {
        if ($page->isSystem()) {
            return response()->json(['success' => false, 'message' => 'System pages cannot be deleted.'], 422);
        }

        $page->delete();

        return response()->json(['success' => true, 'message' => 'Page deleted successfully.']);
    }

    public function updatePageStatus(Request $request, ?Page $page = null)
    {
        $request->validate([
            'id' => 'required_without:page|exists:pages,id',
            'status' => 'required|boolean',
        ]);

        $target = $page ?? Page::findOrFail($request->input('id'));
        $target->update([
            'status' => $request->boolean('status'),
            'published_at' => $request->boolean('status') ? now() : $target->published_at,
        ]);

        return response()->json(['success' => true, 'message' => 'Page status updated.']);
    }

    public function duplicate(Request $request, Page $page)
    {
        $copy = DB::transaction(function () use ($page, $request) {
            $copy = $page->replicate(['system_key']);
            $copy->slug = $this->uniqueSlug($page->slug.'-copy');
            $copy->is_system = false;
            $copy->system_key = null;
            $copy->status = 0;
            $copy->published_at = null;
            $copy->push();

            foreach ($page->translations as $t) {
                PageTranslation::create([
                    'page_id' => $copy->id,
                    'language_code' => $t->language_code,
                    'title' => $t->title.' (copy)',
                    'content' => $t->content,
                    'image_url' => $t->image_url,
                ]);
            }

            $this->snapshot($copy, 'Duplicated from #'.$page->id, $request->user()?->id);

            return $copy;
        });

        return response()->json([
            'success' => true,
            'message' => 'Page duplicated.',
            'edit_url' => route('admin.pages.editor', $copy->id),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Visual builder */
    /* ------------------------------------------------------------------ */

    public function editor(Page $page)
    {
        $palette = $page->isSystem()
            ? PageBlocks::forRegion(PageRegistry::systemDefinitions()[$page->system_key]['blocks'] ?? null)
            : PageBlocks::all();

        $context = [
            'categories' => Category::where('status', 1)->with('translation')->orderBy('id')->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => optional($c->translation)->name ?? $c->slug, 'url' => route('category.show', $c->slug)]),
            'collections' => Collection::marketplace()->orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'url' => url('/collections/'.$c->slug)]),
            'products' => \App\Models\Product::visible()->with('translation')->orderByDesc('id')->limit(200)->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => optional($p->translation)->name ?? $p->slug, 'url' => route('product.show', $p->slug)]),
            'pages' => \App\Models\Page::where('status', 1)->with('translations')->orderBy('id')->limit(100)->get()
                ->map(fn ($pg) => ['id' => $pg->id, 'name' => $pg->title(), 'url' => url('/'.$pg->slug)]),
        ];

        $isProductPage = $page->isSystem() && $page->system_key === 'product';
        $templateOptions = $isProductPage ? \App\Services\Pages\ProductTemplates::options() : [];
        $appearanceFields = $isProductPage ? \App\Services\Pages\ProductPageAppearance::fields() : [];
        $appearance = $isProductPage ? \App\Services\Pages\ProductPageAppearance::forPage($page) : [];

        return view('admin.pages.editor', [
            'page' => $page,
            'blocks' => $page->blocks ?? [],
            'palette' => $palette,
            'context' => $context,
            'isSystem' => $page->isSystem(),
            'regionKey' => $page->system_key,
            'isProductPage' => $isProductPage,
            'templateOptions' => $templateOptions,
            'appearanceFields' => $appearanceFields,
            'appearance' => $appearance,
        ]);
    }

    public function saveBlocks(Request $request, Page $page)
    {
        $allowed = $page->isSystem()
            ? (PageRegistry::systemDefinitions()[$page->system_key]['blocks'] ?? [])
            : null;

        $isProductPage = $page->isSystem() && $page->system_key === 'product';
        $allowedTemplates = $isProductPage
            ? \App\Services\Pages\ProductTemplates::keys()
            : array_keys(Page::TEMPLATES);

        $payload = $request->validate([
            'blocks' => 'nullable|array',
            'title' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:180',
            'template' => 'nullable|in:'.implode(',', $allowedTemplates),
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_image' => 'nullable|string|max:400',
            'appearance' => 'nullable|array',
        ]);

        $clean = PageBlocks::sanitizeBlocks($request->input('blocks', []), $allowed);
        $appearance = $isProductPage
            ? \App\Services\Pages\ProductPageAppearance::sanitize($request->input('appearance', []))
            : null;

        DB::transaction(function () use ($page, $payload, $clean, $request, $isProductPage, $appearance) {
            $updates = [
                'blocks' => $clean,
                'seo_title' => $payload['seo_title'] ?? null,
                'seo_description' => $payload['seo_description'] ?? null,
                'seo_image' => $payload['seo_image'] ?? null,
            ];

            if ($isProductPage && $appearance !== null) {
                $updates['settings'] = $appearance;
            }

            if ($isProductPage && ! empty($payload['template'])) {
                $updates['template'] = $payload['template'];
            }

            if (! $page->isSystem()) {
                $updates['slug'] = $this->uniqueSlug($payload['slug'] ?? $page->slug, $page->id);

                if (! empty($payload['template'])) {
                    $updates['template'] = $payload['template'];
                }
            }

            $page->update($updates);

            if (! empty($payload['title']) && ! $page->isSystem()) {
                $translation = PageTranslation::firstOrNew([
                    'page_id' => $page->id,
                    'language_code' => app()->getLocale(),
                ]);
                $translation->title = $payload['title'];
                $translation->save();
            }

            $this->snapshot($page, 'Saved draft', $request->user()?->id);
        });

        return response()->json(['success' => true, 'message' => 'Draft saved.', 'blocks' => $clean]);
    }

    public function publish(Request $request, Page $page)
    {
        $page->update(['status' => 1, 'published_at' => now()]);
        $this->snapshot($page, 'Published', $request->user()?->id);

        return response()->json(['success' => true, 'message' => 'Page published.']);
    }

    public function unpublish(Request $request, Page $page)
    {
        $page->update(['status' => 0]);
        $this->snapshot($page, 'Unpublished', $request->user()?->id);

        return response()->json(['success' => true, 'message' => 'Page unpublished.']);
    }

    public function preview(Request $request, Page $page, PageRenderer $renderer)
    {
        $blocks = $renderer->render($page);
        $previewTitle = $page->title();
        $content = optional($page->translation())->content;

        return view('admin.pages.preview', compact('page', 'blocks', 'previewTitle', 'content'));
    }

    public function revisions(Page $page)
    {
        return response()->json([
            'data' => $page->revisions()->take(30)->get()->map(fn ($r) => [
                'id' => $r->id,
                'note' => $r->note,
                'status' => $r->status,
                'created_at' => $r->created_at->format('Y-m-d H:i'),
                'author' => optional($r->author)->name,
                'blocks' => count($r->blocks ?? []),
            ]),
        ]);
    }

    public function restore(Request $request, Page $page, $revision)
    {
        $rev = $page->revisions()->whereKey($revision)->firstOrFail();

        $page->update([
            'blocks' => $rev->blocks ?? [],
            'seo_title' => $rev->seo_title,
            'seo_description' => $rev->seo_description,
            'seo_image' => $rev->seo_image,
        ]);

        $this->snapshot($page, 'Restored revision #'.$rev->id, $request->user()?->id);

        return response()->json(['success' => true, 'message' => 'Revision restored.', 'blocks' => $page->blocks]);
    }

    /** Upload an image for a block; returns the storage path for the config. */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        $path = $request->file('image')->store('pages/blocks', 'public');

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'page';
        $slug = $base;
        $i = 1;

        while (
            Page::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()
            || in_array($slug, ['cart', 'checkout'], true)
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function snapshot(Page $page, string $note, ?int $userId = null): void
    {
        $page->revisions()->create([
            'title' => $page->fresh()->title(),
            'blocks' => $page->blocks ?? [],
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
            'seo_image' => $page->seo_image,
            'status' => $page->status ? 'published' : 'draft',
            'note' => $note,
            'created_by' => $userId,
        ]);
    }
}
