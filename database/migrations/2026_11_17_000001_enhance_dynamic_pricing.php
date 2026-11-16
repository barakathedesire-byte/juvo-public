<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_delivery_pricing_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'status')) {
                $table->string('status', 32)->default('active')->after('is_active');
            }
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('logistics_admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('logistics_admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'effective_at')) {
                $table->timestamp('effective_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('effective_at');
            }
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'demand_adjustment_enabled')) {
                $table->boolean('demand_adjustment_enabled')->default(false)->after('version');
            }
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'capacity_adjustment_enabled')) {
                $table->boolean('capacity_adjustment_enabled')->default(false)->after('demand_adjustment_enabled');
            }
            if (! Schema::hasColumn('logistics_delivery_pricing_rules', 'max_adjustment_percent')) {
                $table->decimal('max_adjustment_percent', 5, 2)->default(20)->after('capacity_adjustment_enabled');
            }
        });

        if (! Schema::hasTable('logistics_pricing_snapshots')) {
            Schema::create('logistics_pricing_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->json('rule_ids')->nullable();
                $table->json('breakdown')->nullable();
                $table->decimal('final_fee', 12, 2);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();

                $table->index('order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_pricing_snapshots');
        Schema::table('logistics_delivery_pricing_rules', function (Blueprint $table) {
            foreach (['created_by', 'approved_by'] as $col) {
                if (Schema::hasColumn('logistics_delivery_pricing_rules', $col)) {
                    $table->dropForeign([$col]);
                }
            }
            $columns = ['status', 'created_by', 'approved_by', 'approved_at', 'effective_at', 'version', 'demand_adjustment_enabled', 'capacity_adjustment_enabled', 'max_adjustment_percent'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('logistics_delivery_pricing_rules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
