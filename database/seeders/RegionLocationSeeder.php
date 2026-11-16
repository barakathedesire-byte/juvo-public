<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionLocationSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name' => 'Kampala Region', 'country' => 'Uganda'],
            ['name' => 'Central Region', 'country' => 'Uganda'],
            ['name' => 'Western Region', 'country' => 'Uganda'],
            ['name' => 'Eastern Region', 'country' => 'Uganda'],
            ['name' => 'Northern Region', 'country' => 'Uganda'],
        ];

        foreach ($data as $d) {
            $region = Region::firstOrCreate(['name' => $d['name']], $d + ['is_active' => true, 'sort_order' => 0]);
            if ($region->locations()->exists()) {
                continue;
            }
            Location::create([
                'region_id' => $region->id,
                'name' => 'Central Business District',
                'shipping_fee' => 4700,
                'pickup_fee' => 4700,
                'free_over' => 50000,
                'eta_label' => '1 to 3 days',
                'pickup_eta' => 'Ready for pickup on ' . now()->addDay()->format('j F') . ' if you place your order within the next 5hrs 58mins',
                'door_eta' => '1 to 3 days',
                'is_active' => true,
                'sort_order' => 0,
            ]);
            Location::create([
                'region_id' => $region->id,
                'name' => 'Suburbs',
                'shipping_fee' => 5900,
                'pickup_fee' => 5900,
                'eta_label' => '2 to 4 days',
                'door_eta' => '2 to 4 days',
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }
    }
}
