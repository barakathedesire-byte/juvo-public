<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Refunds may be raised for orders that were paid on delivery, so the
        // payment reference is optional.
        DB::statement('ALTER TABLE refunds MODIFY payment_id BIGINT UNSIGNED NULL');

        Schema::table('refunds', function (Blueprint $table) {
            if (! Schema::hasColumn('refunds', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('payment_id')->constrained('orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('refunds', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('order_id')->constrained('customers')->nullOnDelete();
            }
            if (! Schema::hasColumn('refunds', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('customer_id')->constrained('vendors')->nullOnDelete();
            }
            if (! Schema::hasColumn('refunds', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            foreach (['admin_note', 'vendor_id', 'customer_id', 'order_id'] as $column) {
                if (Schema::hasColumn('refunds', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        DB::statement('ALTER TABLE refunds MODIFY payment_id BIGINT UNSIGNED NOT NULL');
    }
};
