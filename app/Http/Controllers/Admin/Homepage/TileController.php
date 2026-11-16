<?php

namespace App\Http\Controllers\Admin\Homepage;

use App\Http\Controllers\Admin\Homepage\Concerns\HandlesHomepageMedia;
use App\Http\Controllers\Controller;
use App\Models\HomepageTile;
use Illuminate\Http\Request;

class TileController extends Controller
{
    use HandlesHomepageMedia;

    public function index()
    {
        $tiles = HomepageTile::ordered()->get();
        $categories = \App\Models\Category::whereNull('parent_category_id')
            ->with('translation')->orderBy('id')->get();
        $namedCollections = \App\Models\Collection::ordered()->get();

        return view('admin.homepage.tiles.index', compact('tiles', 'categories', 'namedCollections'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) HomepageTile::max('sort_order') + 1;

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request->file('image'), 'tiles');
        }

        HomepageTile::create($data);

        return back()->with('success', 'Tile created.');
    }

    public function update(Request $request, HomepageTile $tile)
    {
        $data = $this->validated($request, $tile);

        if ($request->hasFile('image')) {
            $this->deleteImage($tile->image);
            $data['image'] = $this->storeImage($request->file('image'), 'tiles');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($tile->image);
            $data['image'] = null;
        }

        $tile->update($data);

        return back()->with('success', 'Tile updated.');
    }

    public function destroy(HomepageTile $tile)
    {
        $this->deleteImage($tile->image);
        $tile->delete();

        return back()->with('success', 'Tile deleted.');
    }

    public function toggle(HomepageTile $tile)
    {
        $tile->update(['status' => $tile->status === 'published' ? 'draft' : 'published']);

        return back()->with('success', 'Tile visibility updated.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:homepage_tiles,id',
        ]);

        foreach ($data['order'] as $position => $id) {
            HomepageTile::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['message' => 'Order saved.']);
    }

    protected function validated(Request $request, ?HomepageTile $tile = null): array
    {
        $hex = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        $data = $request->validate([
            'title' => 'required|string|max:120',
            'subtitle' => 'nullable|string|max:190',
            'bg_color' => $hex,
            'text_color' => $hex,
            'size' => 'nullable|in:sm,md,lg',
            'shape' => 'nullable|in:circle,rounded,square',
            'link_type' => 'required|in:category,collection,url',
            'link_value' => 'nullable|string|max:255',
            'status' => 'required|in:draft,published',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'remove_image' => 'sometimes|boolean',
        ]);

        $data['size'] = $data['size'] ?? ($tile->size ?? 'md');
        $data['shape'] = $data['shape'] ?? ($tile->shape ?? 'circle');

        if ($data['link_type'] === 'category' && empty($data['link_value'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'link_value' => 'Please choose a category for this tile.',
            ]);
        }

        if ($data['link_type'] === 'url') {
            $this->assertDestination($data['link_value'] ?? null, 'link_value');
        }

        unset($data['image'], $data['remove_image']);

        return $data;
    }
}
