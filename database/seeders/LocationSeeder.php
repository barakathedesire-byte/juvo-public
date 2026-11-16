<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Region;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $regions = Region::all();
        foreach ($regions as $region) {
            if ($region->locations()->exists()) {
                continue;
            }
            Location::create([
                'region_id' => $region->id,
                'name' => 'Central Business District',
                'shipping_fee' => 5000,
                'pickup_fee' => 4700,
                'eta_label' => '1 to 3 days',
                'pickup_eta' => 'Ready for pickup if ordered within hours',
                'door_eta' => '1 to 3 days',
                'is_active' => true,
                'sort_order' => 0,
            ]);
            Location::create([
                'region_id' => $region->id,
                'name' => 'Suburbs',
                'shipping_fee' => 6500,
                'pickup_fee' => 6000,
                'eta_label' => '2 to 4 days',
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }
    }
}
