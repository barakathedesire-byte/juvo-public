<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdPlacement;
use Illuminate\Http\Request;

class PlacementController extends Controller
{
    public function index()
    {
        $placements = AdPlacement::withCount(['campaigns', 'packages'])
            ->withCount(['campaigns as active_campaigns_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('sort_order')
            ->get();

        return view('admin.ad_manager.placements_index', compact('placements'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['key'] = $data['key'] ?: \Illuminate\Support\Str::slug($data['name'], '_');
        AdPlacement::create($data);

        return back()->with('success', 'Placement created.');
    }

    public function update(Request $request, AdPlacement $placement)
    {
        $placement->update($this->validated($request, false));

        return back()->with('success', 'Placement updated.');
    }

    public function toggle(AdPlacement $placement)
    {
        $placement->update(['is_active' => ! $placement->is_active]);

        return back()->with('success', 'Placement status changed.');
    }

    public function destroy(AdPlacement $placement)
    {
        if ($placement->campaigns()->exists()) {
            return back()->with('error', 'Cannot delete a placement that has campaigns.');
        }

        $placement->delete();

        return back()->with('success', 'Placement deleted.');
    }

    protected function validated(Request $request, bool $creating = true): array
    {
        $rules = [
            'name' => 'required|string|max:190',
            'key' => ($creating ? 'nullable' : 'sometimes').'|string|max:190',
            'description' => 'nullable|string|max:1000',
            'width' => 'nullable|integer|min:0',
            'height' => 'nullable|integer|min:0',
            'base_price' => 'nullable|numeric|min:0',
            'pricing_model' => 'required|in:cpm,cpc,flat',
            'max_active' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ];

        $data = $request->validate($rules);

        return array_merge([
            'key' => null, 'description' => null, 'width' => null, 'height' => null,
            'base_price' => 0, 'max_active' => 0, 'sort_order' => 0,
        ], $data);
    }
}
