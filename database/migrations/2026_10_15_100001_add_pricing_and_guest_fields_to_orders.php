<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('subtotal');
            }
            if (! Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('tax_amount');
            }
            if (! Schema::hasColumn('orders', 'coupon_code')) {
                $table->string('coupon_code', 64)->nullable()->after('discount_amount');
            }
            if (! Schema::hasColumn('orders', 'is_guest')) {
                $table->boolean('is_guest')->default(false)->after('guest_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['tax_amount', 'discount_amount', 'coupon_code', 'is_guest'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
