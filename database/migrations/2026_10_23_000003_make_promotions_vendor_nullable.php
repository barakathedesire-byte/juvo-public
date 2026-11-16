<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-funded (order/basket scope) promotions are owned by Juvo, not a
 * single vendor, so promotions.vendor_id becomes nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE promotions MODIFY vendor_id BIGINT UNSIGNED NULL');
        } elseif (Schema::hasColumn('promotions', 'vendor_id')) {
            Schema::table('promotions', function ($table) {
                $table->unsignedBigInteger('vendor_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE promotions MODIFY vendor_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
