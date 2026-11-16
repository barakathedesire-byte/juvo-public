<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->foreignId('variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
