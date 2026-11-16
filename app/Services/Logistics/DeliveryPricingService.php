<?php

namespace App\Services\Logistics;

use App\Models\Logistics\DispatchConfig;
use App\Models\Logistics\LogisticsDeliveryPricingRule;
use App\Models\Logistics\LogisticsZone;
use App\Models\Location;
use App\Models\Rider\Rider;
use Carbon\Carbon;

class DeliveryPricingService
{
    public function calculate(array $context): array
    {
        $pickup = $context['pickup_location'] ?? null;
        $destination = $context['destination_location'] ?? null;
        $zone = $context['zone'] ?? $this->zoneForLocation($destination?->id ?? $pickup?->id);
        $distanceKm = isset($context['distance_km']) ? (float) $context['distance_km'] : null;
        $deliveryType = $context['delivery_type'] ?? 'door';
        $vehicleType = $context['vehicle_type'] ?? null;
        $subtotal = (float) ($context['subtotal'] ?? 0);

        $rules = $this->eligibleRules($zone?->id, $destination?->id ?? $pickup?->id, $deliveryType, $vehicleType);
        $rule = $rules->first();

        $breakdown = [
            'base_fee' => 0.0,
            'distance_charge' => 0.0,
            'zone_adjustment' => 0.0,
            'vehicle_adjustment' => 0.0,
            'demand_adjustment' => 0.0,
            'capacity_adjustment' => 0.0,
            'peak_surcharge' => 0.0,
            'handling_surcharge' => 0.0,
            'discount' => 0.0,
            'minimum_fee' => 0.0,
        ];

        if ($rule) {
            $breakdown['base_fee'] = (float) $rule->base_fee;
            if ($distanceKm !== null && $rule->per_km_fee > 0) {
                $breakdown['distance_charge'] = round($distanceKm * (float) $rule->per_km_fee, 2);
            }
            $breakdown['peak_surcharge'] = (float) $rule->peak_surcharge;
            $breakdown['handling_surcharge'] = (float) $rule->handling_surcharge;
            $breakdown['minimum_fee'] = (float) $rule->minimum_fee;

            if ($rule->demand_adjustment_enabled) {
                $breakdown['demand_adjustment'] = $this->demandAdjustment($rule, $zone?->id, $destination?->id);
            }
            if ($rule->capacity_adjustment_enabled) {
                $breakdown['capacity_adjustment'] = $this->capacityAdjustment($rule);
            }
            if ($rule->free_delivery_threshold !== null && $subtotal >= (float) $rule->free_delivery_threshold) {
                $breakdown['discount'] = array_sum(array_diff_key($breakdown, ['minimum_fee' => 0, 'discount' => 0]));
            }
        } elseif ($destination) {
            $breakdown['base_fee'] = (float) $destination->shipping_fee;
            if ($destination->free_over !== null && $subtotal >= (float) $destination->free_over) {
                $breakdown['discount'] = $breakdown['base_fee'];
            }
        } elseif ($pickup) {
            $breakdown['base_fee'] = (float) ($pickup->pickup_fee ?? 0);
        }

        $fee = array_sum($breakdown) - $breakdown['minimum_fee']; // minimum applied below
        $fee = max($fee, $breakdown['minimum_fee']);
        if ($fee < 0) {
            $fee = 0;
        }

        return [
            'fee' => round($fee, 2),
            'currency' => 'TZS',
            'rule' => $rule,
            'rule_ids' => $rule ? [$rule->id] : [],
            'breakdown' => $breakdown,
            'explanation' => $rule ? 'Applied pricing rule: '.$rule->name : 'Applied location shipping fee or no matching rule.',
        ];
    }

    public function simulate(array $context): array
    {
        return $this->calculate($context);
    }

    protected function eligibleRules(?int $zoneId, ?int $locationId, string $deliveryType, ?string $vehicleType): \Illuminate\Support\Collection
    {
        $now = now();
        return LogisticsDeliveryPricingRule::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->where('delivery_type', $deliveryType)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
            })
            ->where(function ($q) use ($zoneId, $locationId) {
                $q->whereNull('zone_id')->whereNull('location_id');
                if ($zoneId) {
                    $q->orWhere('zone_id', $zoneId);
                }
                if ($locationId) {
                    $q->orWhere('location_id', $locationId);
                }
            })
            ->when($vehicleType, fn ($q) => $q->where(function ($inner) use ($vehicleType) {
                $inner->whereNull('vehicle_type')->orWhere('vehicle_type', $vehicleType);
            }))
            ->orderBy('priority')
            ->orderByDesc('id')
            ->get();
    }

    protected function zoneForLocation(?int $locationId): ?LogisticsZone
    {
        if (! $locationId) {
            return null;
        }
        return LogisticsZone::whereHas('locations', fn ($q) => $q->where('locations.id', $locationId))->first();
    }

    protected function demandAdjustment(LogisticsDeliveryPricingRule $rule, ?int $zoneId, ?int $locationId): float
    {
        $forecast = app(PredictiveIntelligenceService::class)->demandForecast(7, $locationId);
        $recent = (float) $forecast['recent_daily_average'];
        $expected = collect($forecast['forecast'])->avg('expected');
        if ($recent <= 0) {
            return 0;
        }
        $ratio = $expected / $recent;
        if ($ratio <= 1.1) {
            return 0;
        }
        $percent = min((float) $rule->max_adjustment_percent, ($ratio - 1) * 100);
        $adjustment = (float) $rule->base_fee * ($percent / 100);

        return round($adjustment, 2);
    }

    protected function capacityAdjustment(LogisticsDeliveryPricingRule $rule): float
    {
        $capacity = app(PredictiveIntelligenceService::class)->riderCapacity(7);
        if ($capacity['shortage'] <= 0) {
            return 0;
        }
        $percent = min((float) $rule->max_adjustment_percent, 10);
        $adjustment = (float) $rule->base_fee * ($percent / 100);

        return round($adjustment, 2);
    }
}
