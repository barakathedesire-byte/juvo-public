<?php

namespace App\Services\Logistics;

use App\Models\Logistics\RoutePlan;
use App\Models\Logistics\RouteStop;
use App\Models\Order;
use App\Models\Rider\Rider;
use Illuminate\Support\Facades\Http;

class RouteOptimizationService
{
    public function optimize(Rider $rider, array $orders, ?array $start = null): ?RoutePlan
    {
        $stops = $this->buildStops($orders);
        if (count($stops) < 1) {
            return null;
        }

        $startPoint = $start ?: $this->riderStart($rider);
        if (! $startPoint) {
            // Fall back to first stop as origin; no rider coordinate available.
            $startPoint = $stops[0]['point'];
        }

        $points = array_merge([$startPoint], array_column($stops, 'point'));
        $matrix = $this->distanceMatrix($points);
        $method = $matrix['method'];
        $provider = $matrix['provider'];

        $sequence = $this->nearestNeighbor($matrix['distances'], count($points));
        // sequence indices into stops (0 is start, skip)
        $orderedStops = [];
        foreach (array_slice($sequence, 1) as $idx) {
            $orderedStops[] = $stops[$idx - 1];
        }

        // planned totals
        $plannedDistance = null;
        $plannedDuration = null;
        if ($method === 'osrm' && count($points) >= 2) {
            $route = $this->osrmRoute($points, $sequence);
            if ($route) {
                $plannedDistance = $route['distance_km'];
                $plannedDuration = $route['duration_minutes'];
            }
        }
        if ($plannedDistance === null) {
            $plannedDistance = 0;
            $plannedDuration = 0;
            $prev = 0;
            foreach ($sequence as $i => $idx) {
                if ($i === 0) {
                    $prev = $idx;
                    continue;
                }
                $plannedDistance += ($matrix['distances'][$prev][$idx] ?? 0) / 1000;
                $plannedDuration += (int) round(($matrix['durations'][$prev][$idx] ?? 0) / 60);
                $prev = $idx;
            }
        }

        $plan = RoutePlan::create([
            'rider_id' => $rider->id,
            'status' => 'planned',
            'optimization_method' => $method === 'osrm' ? 'osrm_nearest_neighbor' : 'fallback_nearest_neighbor_straight_line',
            'planned_distance_km' => round($plannedDistance, 2),
            'planned_duration_minutes' => $plannedDuration,
            'provider' => $provider,
            'provider_response' => $matrix['raw'] ?? null,
            'notes' => $method === 'fallback' ? 'Routing provider unavailable or coordinates incomplete. Distances are straight-line fallback estimates, not road distances.' : null,
        ]);

        $prevIndex = 0;
        foreach ($orderedStops as $i => $stop) {
            $dist = $matrix['distances'][$prevIndex][$i + 1] ?? null;
            $dur = $matrix['durations'][$prevIndex][$i + 1] ?? null;
            RouteStop::create([
                'route_plan_id' => $plan->id,
                'order_id' => $stop['order_id'],
                'sequence' => $i + 1,
                'stop_type' => $stop['type'],
                'location_id' => $stop['location_id'],
                'latitude' => $stop['point'][0],
                'longitude' => $stop['point'][1],
                'status' => 'pending',
                'distance_from_previous_km' => $dist !== null ? round($dist / 1000, 2) : null,
                'duration_from_previous_minutes' => $dur !== null ? (int) round($dur / 60) : null,
            ]);
            $prevIndex = $i + 1;
        }

        return $plan->load('stops.order', 'rider');
    }

    protected function buildStops(array $orders): array
    {
        $stops = [];
        foreach ($orders as $order) {
            $pickupCoords = $this->locationCoordinates($order->pickupLocation);
            if ($pickupCoords) {
                $stops[] = [
                    'order_id' => $order->id,
                    'type' => 'pickup',
                    'location_id' => $order->pickupLocation?->id,
                    'point' => $pickupCoords,
                ];
            }
            $deliveryCoords = $this->locationCoordinates($order->shippingAddress?->location);
            if ($deliveryCoords) {
                $stops[] = [
                    'order_id' => $order->id,
                    'type' => 'delivery',
                    'location_id' => $order->shippingAddress?->location_id,
                    'point' => $deliveryCoords,
                ];
            }
        }
        return $stops;
    }

    protected function riderStart(Rider $rider): ?array
    {
        if ($rider->current_lat && $rider->current_lng) {
            return [(float) $rider->current_lat, (float) $rider->current_lng];
        }
        return null;
    }

    protected function locationCoordinates($location): ?array
    {
        if (! $location) {
            return null;
        }
        $settings = $location->logisticsSettings ?? null;
        if ($settings && $settings->latitude && $settings->longitude) {
            return [(float) $settings->latitude, (float) $settings->longitude];
        }
        return null;
    }

    protected function distanceMatrix(array $points): array
    {
        $coords = implode(';', array_map(fn ($p) => $p[1].','.$p[0], $points));
        try {
            $response = Http::timeout(8)->get("https://router.project-osrm.org/table/v1/driving/{$coords}", [
                'annotations' => 'distance,duration',
            ]);
            if ($response->successful()) {
                $data = $response->json();
                if (($data['code'] ?? '') === 'Ok') {
                    return [
                        'method' => 'osrm',
                        'provider' => 'osrm',
                        'distances' => $data['distances'],
                        'durations' => $data['durations'],
                        'raw' => $data,
                    ];
                }
            }
        } catch (\Throwable) {
            // Fall back below.
        }

        $n = count($points);
        $distances = array_fill(0, $n, array_fill(0, $n, 0.0));
        $durations = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    continue;
                }
                $km = $this->haversine($points[$i][0], $points[$i][1], $points[$j][0], $points[$j][1]);
                $distances[$i][$j] = $km * 1000;
                $durations[$i][$j] = ($km / 30) * 3600;
            }
        }

        return [
            'method' => 'fallback',
            'provider' => 'straight_line_fallback',
            'distances' => $distances,
            'durations' => $durations,
            'raw' => null,
        ];
    }

    protected function osrmRoute(array $points, array $sequence): ?array
    {
        $coords = implode(';', array_map(fn ($i) => $points[$i][1].','.$points[$i][0], $sequence));
        try {
            $response = Http::timeout(8)->get("https://router.project-osrm.org/route/v1/driving/{$coords}", [
                'overview' => 'false',
            ]);
            if ($response->successful()) {
                $data = $response->json();
                if (($data['code'] ?? '') === 'Ok' && ! empty($data['routes'][0])) {
                    return [
                        'distance_km' => round($data['routes'][0]['distance'] / 1000, 2),
                        'duration_minutes' => (int) round($data['routes'][0]['duration'] / 60),
                    ];
                }
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    protected function nearestNeighbor(array $distances, int $n): array
    {
        $visited = [0 => true];
        $sequence = [0];
        $current = 0;

        while (count($sequence) < $n) {
            $best = null;
            $bestDistance = INF;
            for ($i = 0; $i < $n; $i++) {
                if (isset($visited[$i])) {
                    continue;
                }
                $d = $distances[$current][$i] ?? INF;
                if ($d < $bestDistance) {
                    $bestDistance = $d;
                    $best = $i;
                }
            }
            if ($best === null) {
                break;
            }
            $visited[$best] = true;
            $sequence[] = $best;
            $current = $best;
        }

        return $sequence;
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * (2 * asin(sqrt($a)));
    }
}
