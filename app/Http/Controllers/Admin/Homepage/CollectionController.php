<?php

namespace App\Http\Controllers\Admin\Homepage;

use App\Http\Controllers\Admin\Homepage\Concerns\HandlesHomepageMedia;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    use HandlesHomepageMedia;
    public function index()
    {
        $collections = Collection::marketplace()->ordered()->get();
        $rules = Collection::RULES;
        $categories = Category::with('translation')->orderBy('id')->get();
        $brands = Brand::with('translation')->orderBy('id')->get();

        return view('admin.homepage.collections.index', compact('collections', 'rules', 'categories', 'brands'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $data['slug'] ?? null);
        $data['sort_order'] = (int) Collection::marketplace()->max('sort_order') + 1;
        $data['vendor_id'] = null;

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request->file('image'), 'collections');
        }

        Collection::create($data);

        return back()->with('success', 'Collection created.');
    }

    public function update(Request $request, Collection $collection)
    {
        $this->ensureMarketplace($collection);
        $data = $this->validated($request, $collection);
        $data['slug'] = $this->uniqueSlug($data['name'], $data['slug'] ?? null, $collection->id);

        if ($request->hasFile('image')) {
            $this->deleteImage($collection->image);
            $data['image'] = $this->storeImage($request->file('image'), 'collections');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($collection->image);
            $data['image'] = null;
        }

        $collection->update($data);

        return back()->with('success', 'Collection updated.');
    }

    public function destroy(Collection $collection)
    {
        $this->ensureMarketplace($collection);
        $collection->delete();

        return back()->with('success', 'Collection deleted.');
    }

    public function toggle(Collection $collection)
    {
        $this->ensureMarketplace($collection);
        $collection->update(['status' => $collection->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', 'Collection status updated.');
    }

    protected function ensureMarketplace(Collection $collection): void
    {
        abort_if($collection->vendor_id !== null, 404);
    }

    protected function validated(Request $request, ?Collection $collection = null): array
    {
        $hex = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:120|alpha_dash',
            'bg_color' => $hex,
            'text_color' => $hex,
            'size' => 'required|in:sm,md,lg',
            'shape' => 'required|in:circle,rounded,square',
            'rule_type' => 'required|in:'.implode(',', array_keys(Collection::RULES)),
            'rule_value' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'remove_image' => 'sometimes|boolean',
        ]);

        unset($data['image'], $data['remove_image']);

        if (in_array($data['rule_type'], ['category', 'brand'], true) && empty($data['rule_value'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'rule_value' => 'Please choose a '.($data['rule_type'] === 'category' ? 'category' : 'brand').' for this rule.',
            ]);
        }

        return $data;
    }

    protected function uniqueSlug(string $name, ?string $requested, ?int $ignoreId = null): string
    {
        $base = Str::slug($requested ?: $name);
        $slug = $base;
        $i = 1;

        while (Collection::marketplace()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
