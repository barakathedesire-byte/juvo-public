<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PromotionPdpSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => "Uganda's Best Prices", 'code' => 'BEST_PRICES', 'discount_type' => 'percentage', 'discount_value' => 0, 'description' => "Uganda's Best Prices | Shop Now"],
            ['name' => 'Exclusive Offers', 'code' => 'EXCLUSIVE_OFFERS', 'discount_type' => 'percentage', 'discount_value' => 0, 'description' => 'Exclusive Offers | Call 0200804020 To Order'],
            ['name' => 'App Exclusive Offers', 'code' => 'APP_EXCLUSIVE', 'discount_type' => 'percentage', 'discount_value' => 0, 'description' => 'Download the app for exclusive offers.'],
        ];

        foreach ($rows as $row) {
            Promotion::firstOrCreate(['code' => $row['code']], array_merge($row, [
                'start_date' => now()->subDays(1),
                'end_date' => now()->addYear(),
                'is_active' => true,
                'usage_limit' => null,
                'min_order_amount' => 0,
            ]));
        }
    }
}
