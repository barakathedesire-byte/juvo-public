<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Logistics\LogisticsRoute;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index()
    {
        $this->canManage();

        $routes = LogisticsRoute::with(['origin.region', 'destination.region'])->latest()->paginate(25);
        $locations = Location::with('region')->active()->ordered()->get();

        return view('logistics.routes.index', compact('routes', 'locations'));
    }

    public function store(Request $request)
    {
        $this->canManage();
        $data = $request->validate([
            'origin_location_id' => 'required|exists:locations,id|different:destination_location_id',
            'destination_location_id' => 'required|exists:locations,id',
            'distance_km' => 'nullable|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'status' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:1000',
        ]);

        $origin = Location::with('logisticsSettings')->find($data['origin_location_id']);
        $destination = Location::with('logisticsSettings')->find($data['destination_location_id']);

        if (empty($data['distance_km'])) {
            $distance = $this->haversine($origin, $destination);
            if ($distance !== null) {
                $data['distance_km'] = $distance;
            }
        }

        $data['status'] = $data['status'] ?? 'draft';

        LogisticsRoute::create($data);

        return redirect()->route('logistics.routes.index')->with('success', 'Route saved.');
    }

    public function destroy(LogisticsRoute $route)
    {
        $this->canManage();
        $route->delete();

        return redirect()->route('logistics.routes.index')->with('success', 'Route deleted.');
    }

    private function haversine(Location $origin, Location $destination): ?float
    {
        $oLat = $origin->logisticsSettings?->latitude;
        $oLng = $origin->logisticsSettings?->longitude;
        $dLat = $destination->logisticsSettings?->latitude;
        $dLng = $destination->logisticsSettings?->longitude;

        if ($oLat === null || $oLng === null || $dLat === null || $dLng === null) {
            return null;
        }

        $earthRadius = 6371;
        $latFrom = deg2rad((float) $oLat);
        $lonFrom = deg2rad((float) $oLng);
        $latTo = deg2rad((float) $dLat);
        $lonTo = deg2rad((float) $dLng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return round($earthRadius * $angle, 2);
    }

    private function canManage(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('zones')), 403);
    }
}
