<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Region;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function byRegion(Request $request, $regionIdOrName)
    {
        $region = is_numeric($regionIdOrName)
            ? Region::find($regionIdOrName)
            : Region::where('name', $regionIdOrName)->first();

        if (! $region || ! $region->is_active) {
            return response()->json(['locations' => []]);
        }

        $locations = Location::where('region_id', $region->id)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'shipping_fee', 'pickup_fee', 'free_over', 'eta_label', 'pickup_eta', 'door_eta', 'is_active']);

        return response()->json(['locations' => $locations]);
    }

    /**
     * Remember the product-page location so checkout can offer pickup
     * stations in the same area without asking for it again.
     */
    public function remember(Request $request)
    {
        $data = $request->validate([
            'region' => 'nullable|string|max:255',
            'location_id' => 'nullable|integer',
        ]);

        $region = trim((string) ($data['region'] ?? ''));

        if ($region === '') {
            $request->session()->forget(['checkout_region', 'checkout_location', 'checkout_location_id']);

            return response()->json(['ok' => true]);
        }

        $request->session()->put('checkout_region', $region);

        $location = ! empty($data['location_id'])
            ? Location::query()
                ->active()
                ->whereKey($data['location_id'])
                ->whereHas('region', fn ($query) => $query->where('name', $region))
                ->first()
            : null;

        if ($location) {
            $request->session()->put('checkout_location_id', $location->id);
            $request->session()->put('checkout_location', $location->name);
        } else {
            $request->session()->forget(['checkout_location', 'checkout_location_id']);
        }

        return response()->json([
            'ok' => true,
            'region' => $region,
            'location' => $location?->name,
        ]);
    }
}
