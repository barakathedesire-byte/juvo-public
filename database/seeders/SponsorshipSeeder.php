<?php

namespace Database\Seeders;

use App\Models\AdCampaign;
use Illuminate\Database\Seeder;

class SponsorshipSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = AdCampaign::where('type', 'sponsored_products')->get();
        if ($campaigns->isEmpty()) {
            AdCampaign::create([
                'vendor_id' => 1,
                'name' => 'Sponsored Products',
                'type' => 'sponsored_products',
                'status' => 'active',
                'budget' => 1000000,
                'spend' => 0,
                'start_date' => now()->subDays(1),
                'end_date' => now()->addMonths(6),
                'placement' => 'product_detail_sponsored',
                'product_ids' => json_encode([]),
            ]);
        }
    }
}
