<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('business_rules')->where('key', 'default_commission_rate')->exists()) {
            DB::table('business_rules')->insert([
                'key' => 'default_commission_rate',
                'name' => 'Default vendor commission rate',
                'description' => 'Platform commission percentage applied to vendor sales when the vendor has no override.',
                'value_type' => 'number',
                'value' => '10',
                'options' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('business_rules')->where('key', 'default_commission_rate')->delete();
    }
};
