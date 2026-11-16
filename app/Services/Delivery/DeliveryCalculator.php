<?php

namespace App\Services\Delivery;

use App\Models\Location;
use App\Services\Logistics\DeliveryPricingService;
use App\Models\Logistics\LogisticsLocationSetting;
use App\Models\Region;
use App\Models\ShippingRate;

class DeliveryCalculator
{
    public function __construct(private readonly DeliveryPricingService $pricing) {}
    public function resolveRegion(string $regionName): ?Region
    {
        return Region::where('name', $regionName)->active()->first();
    }

    public function locationsForRegion($region): array
    {
        if ($region instanceof Region) {
            $regionId = $region->id;
        } elseif (is_numeric($region)) {
            $regionId = $region;
        } else {
            $r = Region::where('name', $region)->first();
            $regionId = $r?->id;
        }

        if (! $regionId) {
            return [];
        }

        return Location::where('region_id', $regionId)->active()->ordered()->get()->toArray();
    }

    public function findLocation($regionName, $locationName): ?Location
    {
        $region = Region::where('name', $regionName)->first();
        if (! $region) {
            return null;
        }

        return Location::where('region_id', $region->id)->where('name', $locationName)->active()->first();
    }

    public function calculateDoorFee($regionName, $locationName, float $subtotal = 0): array
    {
        $location = $this->findLocation($regionName, $locationName);
        if ($location) {
            $settings = $location->logisticsSettings;
            if (! $location->is_active || ($settings && ! $settings->is_covered)) {
                return ['fee' => 0.0, 'eta' => null, 'source' => 'location', 'available' => false];
            }

            $zone = \App\Models\Logistics\LogisticsZone::whereHas('locations', fn ($q) => $q->where('locations.id', $location->id))->first();
            $result = $this->pricing->calculate([
                'pickup_location' => null,
                'destination_location' => $location,
                'zone' => $zone,
                'delivery_type' => 'door',
                'subtotal' => $subtotal,
            ]);

            return [
                'fee' => (float) $result['fee'],
                'eta' => $location->door_eta ?? $location->eta_label ?? null,
                'source' => 'pricing_engine',
                'available' => true,
                'breakdown' => $result['breakdown'],
                'rule' => $result['rule']?->name,
            ];
        }

        $rate = ShippingRate::resolve($regionName, $subtotal);
        if ($rate) {
            return [
                'fee' => (float) $rate->costFor($subtotal),
                'eta' => $rate->eta_label,
                'source' => 'shipping_rate',
                'available' => true,
            ];
        }

        return ['fee' => 0.0, 'eta' => null, 'source' => null, 'available' => false];
    }

    public function calculatePickupFee($regionName, $locationName, float $subtotal = 0): array
    {
        $location = $this->findLocation($regionName, $locationName);
        $settings = $location?->logisticsSettings;
        if ($location && $location->is_active && (! $settings || $settings->is_covered)) {
            $zone = \App\Models\Logistics\LogisticsZone::whereHas('locations', fn ($q) => $q->where('locations.id', $location->id))->first();
            $result = $this->pricing->calculate([
                'pickup_location' => $location,
                'destination_location' => null,
                'zone' => $zone,
                'delivery_type' => 'pickup',
                'subtotal' => $subtotal,
            ]);

            if ($location->pickup_fee === null && ! $result['rule']) {
                return ['fee' => 0.0, 'eta' => null, 'source' => 'location', 'available' => false];
            }

            return [
                'fee' => (float) $result['fee'],
                'eta' => $location->pickup_eta ?? null,
                'source' => 'pricing_engine',
                'available' => true,
                'breakdown' => $result['breakdown'],
                'rule' => $result['rule']?->name,
            ];
        }

        $door = $this->calculateDoorFee($regionName, $locationName, $subtotal);

        return [
            'fee' => $door['fee'],
            'eta' => null,
            'source' => 'fallback',
            'available' => false,
        ];
    }
}
