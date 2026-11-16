<?php

namespace App\Services\Logistics;

use App\Models\Logistics\PredictiveAlert;
use App\Models\Logistics\Prediction;
use App\Models\Order;
use App\Models\Rider\Rider;
use App\Models\ShippingAddress;
use Illuminate\Support\Collection;

class PredictiveIntelligenceService
{
    public function deliveryRisks(int $limit = 25): Collection
    {
        $orders = Order::whereNotNull('logistics_tracking_id')
            ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
            ->with(['assignedRider', 'pickupLocation', 'shippingAddress.location'])
            ->latest()
            ->limit($limit)
            ->get();

        return $orders->map(function (Order $order) {
            $factors = [];
            $score = 20;
            $confidence = 35;

            $rider = $order->assignedRider;
            if ($rider) {
                $active = (int) $rider->assignedOrders()->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])->count();
                if ($active > 2) {
                    $score += 15;
                    $factors[] = 'Rider has '.$active.' active deliveries.';
                }
                $confidence += 15;
            } else {
                $score += 25;
                $factors[] = 'No rider assigned.';
            }

            if (in_array($order->logistics_priority, ['high', 'urgent'], true)) {
                $score += 10;
                $factors[] = 'High-priority order.';
            }

            if ($order->assigned_at && $order->assigned_at->lt(now()->subMinutes(30)) && $order->logistics_status === 'assigned') {
                $score += 20;
                $factors[] = 'Assignment is older than 30 minutes without pickup.';
            }

            if ($order->picked_up_at && $order->picked_up_at->lt(now()->subMinutes(45)) && in_array($order->logistics_status, ['picked_up', 'in_transit'], true)) {
                $score += 15;
                $factors[] = 'Pickup occurred more than 45 minutes ago.';
            }

            $historicalAvg = $this->historicalDeliveryMinutes($order);
            if ($historicalAvg !== null) {
                $confidence += 20;
                $factors[] = 'Historical average delivery duration: '.round($historicalAvg, 1).' minutes.';
            } else {
                $factors[] = 'No comparable historical delivery duration is available.';
            }

            $score = max(0, min(100, $score));
            $risk = $score >= 75 ? 'critical' : ($score >= 50 ? 'high' : ($score >= 30 ? 'moderate' : 'low'));

            $prediction = Prediction::create([
                'prediction_type' => 'delivery_delay',
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'features' => ['priority' => $order->logistics_priority, 'status' => $order->logistics_status],
                'result' => ['risk' => $risk, 'factors' => $factors],
                'score' => $score,
                'risk_level' => $risk,
                'confidence' => $confidence,
                'predicted_at' => now(),
            ]);

            return [
                'order' => $order,
                'score' => $score,
                'risk' => $risk,
                'confidence' => min(100, $confidence),
                'factors' => $factors,
                'prediction_id' => $prediction->id,
            ];
        });
    }

    public function demandForecast(int $days = 7, ?int $locationId = null): array
    {
        $days = max(1, min(30, $days));
        $from = now()->subWeeks(4)->startOfDay();
        $until = now()->subDay()->endOfDay();

        $base = Order::whereBetween('created_at', [$from, $until])
            ->whereNotIn('status', ['canceled', 'returned']);
        if ($locationId) {
            $base->whereHas('shippingAddress', fn ($q) => $q->where('location_id', $locationId));
        }

        $counts = (clone $base)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $byWeekday = array_fill(0, 7, ['sum' => 0, 'days' => 0]);
        $activeDays = 0;
        $totalOrders = 0;

        for ($cursor = $from->copy(); $cursor->lte($until); $cursor->addDay()) {
            $count = (int) ($counts[$cursor->toDateString()] ?? 0);
            $weekday = $cursor->dayOfWeek;
            $byWeekday[$weekday]['sum'] += $count;
            $byWeekday[$weekday]['days']++;
            $totalOrders += $count;
            if ($count > 0) {
                $activeDays++;
            }
        }

        $windowDays = max(1, (int) $from->diffInDays($until) + 1);
        $recentAvg = $totalOrders / $windowDays;
        $hasHistory = $totalOrders > 0;

        $forecast = [];
        for ($i = 1; $i <= $days; $i++) {
            $date = now()->addDays($i)->startOfDay();
            $weekday = $date->dayOfWeek;
            $bucket = $byWeekday[$weekday];
            $avg = $bucket['days'] > 0 ? $bucket['sum'] / $bucket['days'] : $recentAvg;

            if ($bucket['sum'] === 0 && $hasHistory) {
                $avg = $recentAvg;
            }

            $forecast[] = [
                'date' => $date,
                'expected' => round($avg, 2),
                'weekday' => $date->format('D'),
            ];
        }

        $confidence = $activeDays >= 14 ? 70 : ($activeDays >= 7 ? 50 : 35);

        return [
            'forecast' => $forecast,
            'historical_days' => $activeDays,
            'recent_daily_average' => round($recentAvg, 2),
            'confidence' => $confidence,
            'assumption' => 'Forecast uses the average order volume for the same weekday over the last four complete weeks, counting days without orders as zero.',
        ];
    }

    public function failureRisks(): Collection
    {
        return ShippingAddress::query()
            ->selectRaw('state, city, location_id, COUNT(*) as orders')
            ->whereHas('order', fn ($q) => $q->whereNotNull('logistics_tracking_id'))
            ->groupBy('state', 'city', 'location_id')
            ->orderByDesc('orders')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $failed = Order::whereNotNull('logistics_tracking_id')
                    ->where('logistics_status', 'failed')
                    ->whereHas('shippingAddress', fn ($q) => $q->where('state', $row->state)->where('city', $row->city))
                    ->count();
                $rate = $row->orders > 0 ? round(($failed / $row->orders) * 100, 1) : 0;
                return [
                    'state' => $row->state,
                    'city' => $row->city,
                    'orders' => (int) $row->orders,
                    'failed' => $failed,
                    'failure_rate' => $rate,
                ];
            })
            ->filter(fn ($row) => $row['orders'] >= 3)
            ->values();
    }

    public function riderCapacity(int $days = 7): array
    {
        $forecast = $this->demandForecast($days);
        $totalExpected = collect($forecast['forecast'])->sum('expected');
        $availableRiders = Rider::where('is_active', true)->where('kyc_status', 'approved')->count();
        $avgDeliveriesPerRider = Rider::where('is_active', true)
            ->withCount(['assignedOrders as completed' => fn ($q) => $q->where('logistics_status', 'delivered')->where('delivered_at', '>=', now()->subDays(30))])
            ->get()
            ->avg('completed') ?: 1;
        $capacity = max(1, $availableRiders * max(1, $avgDeliveriesPerRider / 30 * $days));
        $shortage = max(0, $totalExpected - $capacity);

        return [
            'expected_orders' => round($totalExpected, 1),
            'available_riders' => $availableRiders,
            'estimated_capacity' => round($capacity, 1),
            'shortage' => round($shortage, 1),
            'average_deliveries_per_rider_30d' => round($avgDeliveriesPerRider, 1),
            'confidence' => $forecast['confidence'],
        ];
    }

    public function generateAlerts(): Collection
    {
        $alerts = collect();

        foreach ($this->deliveryRisks(10) as $risk) {
            if (in_array($risk['risk'], ['high', 'critical'], true)) {
                $alerts->push(PredictiveAlert::create([
                    'alert_type' => 'delivery_delay',
                    'severity' => $risk['risk'] === 'critical' ? 'critical' : 'high',
                    'subject_type' => Order::class,
                    'subject_id' => $risk['order']->id,
                    'title' => 'Order '.$risk['order']->logistics_tracking_id.' has '.$risk['risk'].' delay risk',
                    'description' => implode(' ', $risk['factors']),
                    'factors' => $risk['factors'],
                    'recommended_action' => 'Review assignment, rider workload, and route for this order.',
                    'status' => 'open',
                ]));
            }
        }

        $capacity = $this->riderCapacity(7);
        if ($capacity['shortage'] > 0) {
            $alerts->push(PredictiveAlert::create([
                'alert_type' => 'rider_capacity',
                'severity' => 'high',
                'title' => 'Potential rider capacity shortage',
                'description' => 'Expected '.$capacity['expected_orders'].' orders over 7 days versus estimated capacity of '.$capacity['estimated_capacity'].'.',
                'factors' => [$capacity],
                'recommended_action' => 'Consider adding or reallocating riders in high-demand zones.',
                'status' => 'open',
            ]));
        }

        foreach ($this->failureRisks() as $risk) {
            if ($risk['failure_rate'] >= 25) {
                $alerts->push(PredictiveAlert::create([
                    'alert_type' => 'failure_concentration',
                    'severity' => 'moderate',
                    'subject_type' => 'location',
                    'title' => 'High delivery failure concentration in '.$risk['city'],
                    'description' => $risk['failed'].' of '.$risk['orders'].' deliveries failed ('.$risk['failure_rate'].'%).',
                    'factors' => [$risk],
                    'recommended_action' => 'Review route, rider coverage, and delivery conditions for this location.',
                    'status' => 'open',
                ]));
            }
        }

        return $alerts;
    }

    public function recommendations(): Collection
    {
        $recommendations = collect();
        $capacity = $this->riderCapacity(7);
        if ($capacity['shortage'] > 0) {
            $recommendations->push([
                'title' => 'Increase rider coverage',
                'reason' => 'Expected demand exceeds estimated rider capacity over the next 7 days.',
                'metrics' => $capacity,
                'action' => 'Add or reallocate riders in high-demand zones.',
                'confidence' => $capacity['confidence'],
            ]);
        }

        foreach ($this->failureRisks()->take(3) as $risk) {
            if ($risk['failure_rate'] >= 20) {
                $recommendations->push([
                    'title' => 'Investigate delivery failures in '.$risk['city'],
                    'reason' => 'Failure rate is '.$risk['failure_rate'].'% across '.$risk['orders'].' deliveries.',
                    'metrics' => $risk,
                    'action' => 'Review routes, rider assignments, and local delivery conditions.',
                    'confidence' => 60,
                ]);
            }
        }

        $demand = $this->demandForecast(7);
        $peak = collect($demand['forecast'])->sortByDesc('expected')->first();
        if ($peak && $peak['expected'] > $demand['recent_daily_average'] * 1.25) {
            $recommendations->push([
                'title' => 'Prepare for peak demand on '.$peak['date']->format('D, M j'),
                'reason' => 'Forecast demand is above the recent daily average.',
                'metrics' => ['expected' => $peak['expected'], 'recent_average' => $demand['recent_daily_average']],
                'action' => 'Schedule additional riders and review zone coverage.',
                'confidence' => $demand['confidence'],
            ]);
        }

        return $recommendations;
    }

    protected function historicalDeliveryMinutes(Order $order): ?float
    {
        $query = Order::where('logistics_status', 'delivered')
            ->whereNotNull('picked_up_at')
            ->whereNotNull('delivered_at');

        if ($order->shippingAddress?->location_id) {
            $query->whereHas('shippingAddress', fn ($q) => $q->where('location_id', $order->shippingAddress->location_id));
        } elseif ($order->shippingAddress?->state) {
            $query->whereHas('shippingAddress', fn ($q) => $q->where('state', $order->shippingAddress->state));
        }

        $orders = $query->latest('delivered_at')->limit(20)->get();
        if ($orders->isEmpty()) {
            return null;
        }

        return $orders->avg(fn (Order $o) => $o->picked_up_at->diffInMinutes($o->delivered_at));
    }
}
