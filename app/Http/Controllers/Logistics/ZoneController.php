<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Logistics\LogisticsHub;
use App\Models\Logistics\LogisticsZone;
use App\Models\Rider\Rider;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index()
    {
        $this->canManage();

        $regions = \App\Models\Region::with(['locations.logisticsSettings.assignedRider', 'locations.logisticsSettings'])
            ->ordered()
            ->get();
        $zones = LogisticsZone::with(['locations', 'riders', 'primaryHub'])->latest()->get();
        $hubs = LogisticsHub::orderBy('name')->get();
        $riders = Rider::where('is_active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $locations = Location::with('region')->active()->ordered()->get();

        return view('logistics.zones.index', compact('regions', 'zones', 'hubs', 'riders', 'locations'));
    }

    public function store(Request $request)
    {
        $this->canManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:64|unique:logistics_zones,code',
            'description' => 'nullable|string|max:1000',
            'polygon' => 'nullable|string',
            'primary_hub_id' => 'nullable|exists:logistics_hubs,id',
            'capacity' => 'nullable|integer|min:0',
            'service_restrictions' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'exists:locations,id',
            'rider_ids' => 'nullable|array',
            'rider_ids.*' => 'exists:riders,id',
        ]);

        $zone = LogisticsZone::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'polygon' => $this->decodePolygon($data['polygon'] ?? null),
            'primary_hub_id' => $data['primary_hub_id'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'service_restrictions' => $data['service_restrictions'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $zone->locations()->sync($data['location_ids'] ?? []);
        $zone->riders()->sync($data['rider_ids'] ?? []);

        return redirect()->route('logistics.zones.index')->with('success', 'Delivery zone created.');
    }

    public function update(Request $request, LogisticsZone $zone)
    {
        $this->canManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:64|unique:logistics_zones,code,'.$zone->id,
            'description' => 'nullable|string|max:1000',
            'polygon' => 'nullable|string',
            'primary_hub_id' => 'nullable|exists:logistics_hubs,id',
            'capacity' => 'nullable|integer|min:0',
            'service_restrictions' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'exists:locations,id',
            'rider_ids' => 'nullable|array',
            'rider_ids.*' => 'exists:riders,id',
        ]);

        $zone->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'polygon' => $this->decodePolygon($data['polygon'] ?? null),
            'primary_hub_id' => $data['primary_hub_id'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'service_restrictions' => $data['service_restrictions'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $zone->locations()->sync($data['location_ids'] ?? []);
        $zone->riders()->sync($data['rider_ids'] ?? []);

        return redirect()->route('logistics.zones.index')->with('success', 'Delivery zone updated.');
    }

    public function destroy(LogisticsZone $zone)
    {
        $this->canManage();
        $zone->delete();

        return redirect()->route('logistics.zones.index')->with('success', 'Delivery zone deleted.');
    }

    public function updateLocationSettings(\Illuminate\Http\Request $request, \App\Models\Location $location)
    {
        $this->canManage();
        $data = $request->validate([
            'is_covered' => 'nullable|boolean',
            'delivery_zone' => 'nullable|string|max:120',
            'hub_name' => 'nullable|string|max:120',
            'assigned_rider_id' => 'nullable|exists:riders,id',
            'delivery_fee_override' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'service_notes' => 'nullable|string|max:1000',
        ]);

        \App\Models\Logistics\LogisticsLocationSetting::updateOrCreate(
            ['location_id' => $location->id],
            [
                'is_covered' => $request->boolean('is_covered'),
                'delivery_zone' => $data['delivery_zone'] ?? null,
                'hub_name' => $data['hub_name'] ?? null,
                'assigned_rider_id' => $data['assigned_rider_id'] ?? null,
                'delivery_fee_override' => $data['delivery_fee_override'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'service_notes' => $data['service_notes'] ?? null,
            ]
        );

        return redirect()->route('logistics.zones.index')->with('success', 'Logistics settings updated for '.$location->name.'.');
    }

    private function decodePolygon(?string $value): ?array
    {
        if (! $value) {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function canManage(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('zones')), 403);
    }
}
