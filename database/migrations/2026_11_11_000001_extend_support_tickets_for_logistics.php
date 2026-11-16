<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('product_id')->constrained('orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'rider_id')) {
                $table->foreignId('rider_id')->nullable()->after('order_id')->constrained('riders')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'assigned_logistics_admin_id')) {
                $table->foreignId('assigned_logistics_admin_id')->nullable()->after('assigned_to')->constrained('logistics_admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'dispute_type')) {
                $table->string('dispute_type', 64)->nullable()->after('category');
            }
            if (! Schema::hasColumn('support_tickets', 'resolution_category')) {
                $table->string('resolution_category', 64)->nullable()->after('status');
            }
            if (! Schema::hasColumn('support_tickets', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable()->after('resolution_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            foreach (['order_id', 'rider_id', 'assigned_logistics_admin_id'] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropForeign([$column]);
                    $table->dropColumn($column);
                }
            }
            foreach (['dispute_type', 'resolution_category', 'resolution_notes'] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
