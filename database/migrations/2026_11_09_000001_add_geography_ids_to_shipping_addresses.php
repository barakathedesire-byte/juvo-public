<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_addresses', 'region_id')) {
                $table->foreignId('region_id')->nullable()->after('state')->constrained('regions')->nullOnDelete();
            }
            if (! Schema::hasColumn('shipping_addresses', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('region_id')->constrained('locations')->nullOnDelete();
            }
        });

        DB::table('shipping_addresses')
            ->whereNull('region_id')
            ->update([
                'region_id' => DB::raw('(SELECT regions.id FROM regions WHERE regions.name = shipping_addresses.state LIMIT 1)'),
            ]);

        DB::table('shipping_addresses')
            ->whereNull('location_id')
            ->update([
                'location_id' => DB::raw('(SELECT locations.id FROM locations INNER JOIN regions ON regions.id = locations.region_id WHERE locations.name = shipping_addresses.city AND regions.name = shipping_addresses.state LIMIT 1)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('shipping_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_addresses', 'location_id')) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            }
            if (Schema::hasColumn('shipping_addresses', 'region_id')) {
                $table->dropForeign(['region_id']);
                $table->dropColumn('region_id');
            }
        });
    }
};
