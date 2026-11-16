<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\StorefrontTemplate;
use App\Models\Vendor;
use App\Services\Store\StoreBlocks;
use App\Services\Store\StoreRenderer;
use Illuminate\Http\Request;

class StorefrontBuilderController extends Controller
{
    public function __construct(protected StoreRenderer $renderer)
    {
        $this->middleware(function ($request, $next) {
            abort_unless(\App\Support\Access\AdminTabRegistry::canSee('pages'), 403);

            return $next($request);
        });

        $this->middleware(function ($request, $next) {
            abort_unless(\App\Support\Access\AdminTabRegistry::canWrite('pages'), 403);

            return $next($request);
        }, ['only' => ['store', 'saveDraft', 'publish', 'unpublish', 'destroy']]);
    }

    public function index()
    {
        $templates = StorefrontTemplate::with('vendor')
            ->orderByDesc('scope')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.storefront.index', [
            'templates' => $templates,
            'vendors' => Vendor::orderBy('name')->get(['id', 'name', 'shop_name']),
        ]);
    }

    public function create()
    {
        return view('admin.storefront.create', [
            'vendors' => Vendor::orderBy('name')->get(['id', 'name', 'shop_name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'scope' => 'required|in:global,vendor',
            'vendor_id' => 'nullable|required_if:scope,vendor|exists:vendors,id',
        ]);

        $template = StorefrontTemplate::create([
            'name' => $data['name'],
            'scope' => $data['scope'],
            'vendor_id' => $data['scope'] === StorefrontTemplate::SCOPE_VENDOR ? $data['vendor_id'] : null,
            'blocks' => [],
            'status' => 0,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.storefront.edit', $template->id)
            ->with('success', 'Template created. Add sections and publish when ready.');
    }

    public function edit(StorefrontTemplate $template)
    {
        return view('admin.storefront.edit', [
            'template' => $template,
            'regions' => StorefrontTemplate::REGIONS,
            'regionBlocks' => $template->sanitizedRegions(),
            'blockTypes' => StoreBlocks::all(),
            'vendors' => Vendor::orderBy('name')->get(['id', 'name', 'shop_name']),
            'previewVendor' => $template->vendor,
        ]);
    }

    public function saveDraft(Request $request, StorefrontTemplate $template)
    {
        $template->blocks = $this->parseRegions($request);
        $template->save();

        return response()->json(['ok' => true, 'count' => $this->countBlocks($template->blocks)]);
    }

    public function publish(Request $request, StorefrontTemplate $template)
    {
        $template->blocks = $this->parseRegions($request);
        $template->status = 1;
        $template->published_at = now();
        $template->save();

        return response()->json([
            'ok' => true,
            'count' => $this->countBlocks($template->blocks),
            'published_at' => $template->published_at->toIso8601String(),
        ]);
    }

    public function unpublish(StorefrontTemplate $template)
    {
        $template->status = 0;
        $template->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(StorefrontTemplate $template)
    {
        $template->delete();

        return response()->json(['ok' => true]);
    }

    public function preview(Request $request, StorefrontTemplate $template)
    {
        $vendor = $request->filled('vendor_id')
            ? Vendor::findOrFail($request->integer('vendor_id'))
            : $template->vendor;

        $vendor = $vendor ?: Vendor::where('is_approved', 1)->orderBy('name')->first();

        abort_unless($vendor, 404, 'No vendor available for preview.');

        $region = $request->string('region')->trim()->toString() ?: 'home';

        abort_unless(array_key_exists($region, StorefrontTemplate::REGIONS), 404);

        $shop = Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => $vendor->shop_name ?: $vendor->name, 'status' => 'active', 'slug' => str()->slug($vendor->name.'-store')]
        );

        $blocks = $this->renderer->renderBlocks($template->sanitizedBlocksFor($region), $shop, $vendor);

        return view('admin.storefront.preview', [
            'template' => $template,
            'vendor' => $vendor,
            'shop' => $shop,
            'blocks' => $blocks,
            'region' => $region,
        ]);
    }

    /**
     * Sanitise the full region map from the builder. A legacy single-region
     * payload (layout) is treated as the Home region.
     */
    protected function parseRegions(Request $request): array
    {
        $raw = $request->input('regions');

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw)) {
            return ['home' => $this->parseLayout($request)];
        }

        $out = [];

        foreach (array_keys(StorefrontTemplate::REGIONS) as $region) {
            $blocks = $raw[$region] ?? [];
            $out[$region] = StoreBlocks::sanitize(is_array($blocks) ? $blocks : []);
        }

        return $out;
    }

    protected function countBlocks($blocks): int
    {
        if (! is_array($blocks)) {
            return 0;
        }

        return array_is_list($blocks)
            ? count($blocks)
            : array_sum(array_map('count', array_map(fn ($r) => is_array($r) ? $r : [], $blocks)));
    }

    protected function parseLayout(Request $request): array
    {
        $raw = $request->input('layout');

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = [];
        }

        if (! is_array($decoded)) {
            $decoded = [];
        }

        return StoreBlocks::sanitize($decoded);
    }
}
