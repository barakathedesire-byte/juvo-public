<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Region;
use App\Models\Logistics\LogisticsHub;
use App\Models\Logistics\LogisticsZone;
use Illuminate\Http\Request;

class HubController extends Controller
{
    public function index()
    {
        $this->canManage();

        $hubs = LogisticsHub::with(['location.region', 'zone'])->latest()->paginate(25);
        $locations = Location::with('region')->active()->ordered()->get();
        $zones = LogisticsZone::orderBy('name')->get();
        $regions = Region::ordered()->get();

        return view('logistics.hubs.index', compact('hubs', 'locations', 'zones', 'regions'));
    }

    public function store(Request $request)
    {
        $this->canManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:64|unique:logistics_hubs,code',
            'location_id' => 'nullable|exists:locations,id',
            'zone_id' => 'nullable|exists:logistics_zones,id',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'operating_hours' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        LogisticsHub::create($data);

        return redirect()->route('logistics.hubs.index')->with('success', 'Hub created.');
    }

    public function update(Request $request, LogisticsHub $hub)
    {
        $this->canManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:64|unique:logistics_hubs,code,'.$hub->id,
            'location_id' => 'nullable|exists:locations,id',
            'zone_id' => 'nullable|exists:logistics_zones,id',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'operating_hours' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $hub->update($data);

        return redirect()->route('logistics.hubs.index')->with('success', 'Hub updated.');
    }

    public function destroy(LogisticsHub $hub)
    {
        $this->canManage();
        $hub->delete();

        return redirect()->route('logistics.hubs.index')->with('success', 'Hub deleted.');
    }

    private function canManage(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('zones')), 403);
    }
}
