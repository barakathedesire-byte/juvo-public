<?php

namespace App\Services\Logistics;

use App\Models\Logistics\DispatchConfig;
use App\Models\Logistics\LogisticsLocationSetting;
use App\Models\Order;
use App\Models\Rider\Rider;

class DispatchRecommendationService
{
    public function recommend(Order $order, iterable $riders): array
    {
        $config = DispatchConfig::current();
        $weights = $config->scoring_weights ?: [];
        $destinationCity = strtolower(trim((string) ($order->shippingAddress?->city ?? '')));
        $destinationState = strtolower(trim((string) ($order->shippingAddress?->state ?? '')));
        $pickupLocation = $order->pickupLocation;
        $pickupCoords = $this->locationCoordinates($pickupLocation);

        $candidates = [];

        foreach ($riders as $rider) {
            if (! $rider->isEligibleForWork()) {
                continue;
            }

            $active = (int) ($rider->active_deliveries_count ?? 0);
            if ($active >= $config->max_active_assignments) {
                continue;
            }

            $factors = [];
            $score = 0;

            // Availability
            $availabilityScore = $rider->is_online ? 100 : 40;
            $weighted = $this->weighted($availabilityScore, $weights['availability'] ?? 25);
            $score += $weighted;
            $factors[] = ['factor' => 'availability', 'score' => $availabilityScore, 'weight' => $weights['availability'] ?? 25, 'contribution' => $weighted, 'detail' => $rider->is_online ? 'Rider is online.' : 'Rider is offline.'];

            // Workload
            $workloadScore = max(0, 100 - ($active * 25));
            $weighted = $this->weighted($workloadScore, $weights['workload'] ?? 20);
            $score += $weighted;
            $factors[] = ['factor' => 'workload', 'score' => $workloadScore, 'weight' => $weights['workload'] ?? 20, 'contribution' => $weighted, 'detail' => $active.' active assignment(s).'];

            // Zone/service area
            $zoneScore = 0;
            $riderCity = strtolower(trim((string) $rider->city));
            $riderState = strtolower(trim((string) $rider->state));
            if ($destinationCity !== '' && $riderCity === $destinationCity) {
                $zoneScore += 70;
            }
            if ($destinationState !== '' && $riderState === $destinationState) {
                $zoneScore += 30;
            }
            $zoneScore = min(100, $zoneScore);
            $weighted = $this->weighted($zoneScore, $weights['zone'] ?? 15);
            $score += $weighted;
            $factors[] = ['factor' => 'zone', 'score' => $zoneScore, 'weight' => $weights['zone'] ?? 15, 'contribution' => $weighted, 'detail' => $zoneScore > 0 ? 'Service-area match.' : 'No service-area match.'];

            // Vehicle compatibility
            $vehicleScore = 50;
            $factors[] = ['factor' => 'vehicle', 'score' => $vehicleScore, 'weight' => $weights['vehicle'] ?? 5, 'contribution' => $this->weighted($vehicleScore, $weights['vehicle'] ?? 5), 'detail' => 'Vehicle compatibility not explicitly configured; neutral score used.'];
            $score += $this->weighted($vehicleScore, $weights['vehicle'] ?? 5);

            // Performance
            $performanceScore = min(100, (int) round((float) $rider->rating * 20));
            $weighted = $this->weighted($performanceScore, $weights['performance'] ?? 10);
            $score += $weighted;
            $factors[] = ['factor' => 'performance', 'score' => $performanceScore, 'weight' => $weights['performance'] ?? 10, 'contribution' => $weighted, 'detail' => 'Rating '.$rider->rating.'.'];

            // Priority
            $priorityScore = match ($order->logistics_priority) {
                'urgent' => 100,
                'high' => 75,
                'normal' => 50,
                default => 25,
            };
            $weighted = $this->weighted($priorityScore, $weights['priority'] ?? 5);
            $score += $weighted;
            $factors[] = ['factor' => 'priority', 'score' => $priorityScore, 'weight' => $weights['priority'] ?? 5, 'contribution' => $weighted, 'detail' => 'Order priority: '.($order->logistics_priority ?: 'normal').'.'];

            // Distance
            $distance = null;
            $distanceScore = 50;
            if ($pickupCoords && $rider->current_lat && $rider->current_lng) {
                $distance = $this->haversine((float) $rider->current_lat, (float) $rider->current_lng, $pickupCoords[0], $pickupCoords[1]);
                $distanceScore = max(0, 100 - (int) round($distance * 2));
            }
            $weighted = $this->weighted($distanceScore, $weights['distance'] ?? 20);
            $score += $weighted;
            $factors[] = ['factor' => 'distance', 'score' => $distanceScore, 'weight' => $weights['distance'] ?? 20, 'contribution' => $weighted, 'detail' => $distance === null ? 'Rider or pickup coordinates unavailable.' : 'Estimated rider-to-pickup distance: '.number_format($distance, 2).' km.'];

            $candidates[] = [
                'rider' => $rider,
                'score' => round($score, 2),
                'distance_km' => $distance,
                'factors' => $factors,
                'eligible' => true,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates;
    }

    protected function weighted(int $score, int $weight): int
    {
        return (int) round($score * ($weight / 100));
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

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * (2 * asin(sqrt($a)));
    }
}
