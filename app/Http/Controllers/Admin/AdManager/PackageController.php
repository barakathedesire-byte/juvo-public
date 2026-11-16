<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdPackage;
use App\Models\AdPlacement;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index()
    {
        $packages = AdPackage::with('placement')->orderBy('sort_order')->get();
        $placements = AdPlacement::orderBy('sort_order')->get();

        return view('admin.ad_manager.packages_index', compact('packages', 'placements'));
    }

    public function store(Request $request)
    {
        AdPackage::create($this->validated($request));

        return back()->with('success', 'Package created.');
    }

    public function update(Request $request, AdPackage $package)
    {
        $package->update($this->validated($request));

        return back()->with('success', 'Package updated.');
    }

    public function toggle(AdPackage $package)
    {
        $package->update(['is_active' => ! $package->is_active]);

        return back()->with('success', 'Package status changed.');
    }

    public function destroy(AdPackage $package)
    {
        $package->delete();

        return back()->with('success', 'Package deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:190',
            'description' => 'nullable|string|max:1000',
            'placement_id' => 'nullable|exists:ad_placements,id',
            'duration_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'impressions_included' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }
}
