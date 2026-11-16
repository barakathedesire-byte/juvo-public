<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order/basket-scope promotions are not tied to a single product, so the
 * promotions.product_id column becomes nullable (the existing foreign key still
 * permits NULL values).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE promotions MODIFY product_id BIGINT UNSIGNED NULL');
        } elseif (Schema::hasColumn('promotions', 'product_id')) {
            Schema::table('promotions', function ($table) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE promotions MODIFY product_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
