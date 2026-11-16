<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mobile money gateway is the code the storefront and the vendor portal
     * use to select the mobile money method. The row is inserted (never
     * updated) so an operator who enabled or disabled it in the admin screen
     * keeps that choice across deploys.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            return;
        }

        if (DB::table('payment_gateways')->where('code', 'mobilemoney')->exists()) {
            return;
        }

        DB::table('payment_gateways')->insert([
            'name' => 'Mobile Money',
            'code' => 'mobilemoney',
            'description' => 'Pay with M-Pesa, Mixx by Yas, Airtel Money, HaloPesa or T-Pesa. The aggregator pushes the PIN prompt to the handset.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            return;
        }

        DB::table('payment_gateways')->where('code', 'mobilemoney')->delete();
    }
};
