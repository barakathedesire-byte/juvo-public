<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run()
    {
        if (DB::table('orders')->whereIn('guest_email', ['guest1@example.com', 'guest2@example.com'])->exists()) {
            return;
        }

        $products = DB::table('products')->orderBy('id')->limit(2)->pluck('id')->all();

        if ($products === []) {
            $this->command?->warn('OrderSeeder: no products found, skipping demo orders.');

            return;
        }

        $order1Id = DB::table('orders')->insertGetId([
            'customer_id' => null,
            'guest_email' => 'guest1@example.com',
            'total_amount' => 300,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order2Id = DB::table('orders')->insertGetId([
            'customer_id' => null,
            'guest_email' => 'guest2@example.com',
            'total_amount' => 150,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $details = [
            [
                'order_id' => $order1Id,
                'product_id' => $products[0],
                'quantity' => 2,
                'price' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order2Id,
                'product_id' => $products[0],
                'quantity' => 3,
                'price' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        if (isset($products[1])) {
            $details[] = [
                'order_id' => $order1Id,
                'product_id' => $products[1],
                'quantity' => 1,
                'price' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('order_details')->insert($details);
    }
}
