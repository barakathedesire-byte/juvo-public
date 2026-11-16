<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Region;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegionLocationController extends Controller
{
    public function index()
    {
        $regions = Region::with('locations')->ordered()->get();

        $orderCounts = \App\Models\ShippingAddress::query()
            ->whereNotNull('state')
            ->selectRaw('state, COUNT(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        $regionDependencies = $regions->mapWithKeys(fn ($region) => [
            $region->id => [
                'locations' => $region->locations->count(),
                'orders' => (int) ($orderCounts[$region->name] ?? 0),
            ],
        ])->all();

        return view('admin.regions.index', compact('regions', 'regionDependencies'));
    }

    public function storeRegion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('region_create_error', true);
        }

        $region = Region::create([
            'name' => $request->input('name'),
            'country' => $request->input('country', 'Tanzania'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLogger::record('admin.region.created', $region, [], $region->toArray(), null, auth()->user());

        return back()->with('success', 'Region created successfully.');
    }

    public function updateRegion(Request $request, Region $region)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('region_edit_'.$region->id, true);
        }

        $before = $region->toArray();
        $region->update($request->only(['name', 'country', 'sort_order', 'is_active']));

        AuditLogger::record('admin.region.updated', $region, $before, $region->fresh()->toArray(), null, auth()->user());

        return back()->with('success', 'Region updated successfully.');
    }

    public function storeLocation(Request $request, Region $region)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'shipping_fee' => 'nullable|numeric|min:0',
            'pickup_fee' => 'nullable|numeric|min:0',
            'free_over' => 'nullable|numeric|min:0',
            'eta_label' => 'nullable|string|max:255',
            'pickup_eta' => 'nullable|string|max:255',
            'door_eta' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('location_create_'.$region->id, true);
        }

        $location = $region->locations()->create([
            'name' => $request->input('name'),
            'postal_code' => $request->input('postal_code'),
            'shipping_fee' => $request->input('shipping_fee', 0),
            'pickup_fee' => $request->input('pickup_fee'),
            'free_over' => $request->input('free_over'),
            'eta_label' => $request->input('eta_label'),
            'pickup_eta' => $request->input('pickup_eta'),
            'door_eta' => $request->input('door_eta'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLogger::record('admin.location.created', $location, [], $location->toArray(), null, auth()->user());

        return back()->with('success', 'Location created successfully.');
    }

    public function updateLocation(Request $request, Location $location)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'shipping_fee' => 'nullable|numeric|min:0',
            'pickup_fee' => 'nullable|numeric|min:0',
            'free_over' => 'nullable|numeric|min:0',
            'eta_label' => 'nullable|string|max:255',
            'pickup_eta' => 'nullable|string|max:255',
            'door_eta' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('location_edit_'.$location->id, true);
        }

        $before = $location->toArray();
        $location->update($request->only([
            'name', 'postal_code', 'shipping_fee', 'pickup_fee',
            'free_over', 'eta_label', 'pickup_eta', 'door_eta', 'sort_order', 'is_active',
        ]));

        AuditLogger::record('admin.location.updated', $location, $before, $location->fresh()->toArray(), null, auth()->user());

        return back()->with('success', 'Location updated successfully.');
    }

    public function destroyRegion(Region $region)
    {
        if ($region->locations()->exists()) {
            return back()->with('error', 'Remove this region\'s locations before deleting the region.');
        }

        $orders = \App\Models\ShippingAddress::where('state', $region->name)->count();

        if ($orders > 0) {
            return back()->with('error', 'This region is used by '.$orders.' order(s) and cannot be deleted. Deactivate it instead.');
        }

        AuditLogger::record('admin.region.deleted', $region, $region->toArray(), [], null, auth()->user());
        $region->delete();

        return back()->with('success', 'Region deleted successfully.');
    }

    public function destroyLocation(Location $location)
    {
        $orders = \App\Models\ShippingAddress::where('location_id', $location->id)->count();
        if ($orders > 0) {
            return back()->with('error', 'This location is used by '.$orders.' order(s) and cannot be deleted. Deactivate it instead.');
        }

        AuditLogger::record('admin.location.deleted', $location, $location->toArray(), [], null, auth()->user());
        $location->delete();

        return back()->with('success', 'Location deleted successfully.');
    }
}
