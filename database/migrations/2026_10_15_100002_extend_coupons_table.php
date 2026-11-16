<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'description')) {
                $table->string('description')->nullable()->after('type');
            }
            if (! Schema::hasColumn('coupons', 'min_order_value')) {
                $table->decimal('min_order_value', 12, 2)->default(0)->after('description');
            }
            if (! Schema::hasColumn('coupons', 'max_discount')) {
                $table->decimal('max_discount', 12, 2)->nullable()->after('min_order_value');
            }
            if (! Schema::hasColumn('coupons', 'usage_limit')) {
                $table->unsignedInteger('usage_limit')->nullable()->after('max_discount');
            }
            if (! Schema::hasColumn('coupons', 'used_count')) {
                $table->unsignedInteger('used_count')->default(0)->after('usage_limit');
            }
            if (! Schema::hasColumn('coupons', 'per_customer_limit')) {
                $table->unsignedInteger('per_customer_limit')->nullable()->after('used_count');
            }
            if (! Schema::hasColumn('coupons', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('per_customer_limit');
            }
            if (! Schema::hasColumn('coupons', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            foreach ([
                'description', 'min_order_value', 'max_discount', 'usage_limit',
                'used_count', 'per_customer_limit', 'starts_at', 'is_active',
            ] as $column) {
                if (Schema::hasColumn('coupons', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
