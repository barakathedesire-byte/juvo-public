<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('code')->constrained('vendors')->nullOnDelete();
            }
            if (! Schema::hasColumn('coupons', 'title')) {
                $table->string('title')->nullable()->after('vendor_id');
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('promotions', 'title')) {
                $table->string('title')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('promotions', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('active');
            }
            if (! Schema::hasColumn('promotions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'vendor_id')) {
                $table->dropConstrainedForeignId('vendor_id');
            }
            if (Schema::hasColumn('coupons', 'title')) {
                $table->dropColumn('title');
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            foreach (['title', 'starts_at', 'ends_at'] as $column) {
                if (Schema::hasColumn('promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
