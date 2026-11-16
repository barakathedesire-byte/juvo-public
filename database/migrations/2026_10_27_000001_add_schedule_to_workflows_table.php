<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('schedule', 64)->nullable()->after('trigger_type');
            $table->index(['is_active', 'trigger_type', 'next_run_at'], 'workflows_schedule_due_index');
        });

        $schedules = [
            'recompute_associations_daily' => '0 2 * * *',
            'refresh_marketplace_metrics_daily' => '0 0 * * *',
            'detect_anomalies_daily' => '0 1 * * *',
            'reindex_search_index_daily' => '0 3 * * *',
            'expire_promotions_hourly' => '0 * * * *',
        ];

        foreach ($schedules as $key => $expression) {
            DB::table('workflows')->where('key', $key)->update(['schedule' => $expression]);
        }

        DB::table('workflows')->whereNull('trigger_type')->update(['trigger_type' => 'event']);

        $actions = [
            'Vendor onboarding approval' => 'vendor_onboarding_approval',
            'Ad campaign approval' => 'ad_campaign_approval',
            'Order ready for pickup' => 'order_ready_for_pickup',
            'Low stock alert' => 'low_stock_alert',
        ];

        foreach ($actions as $name => $action) {
            DB::table('workflows')->where('name', $name)->update(['action' => $action]);
        }
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropIndex('workflows_schedule_due_index');
            $table->dropColumn('schedule');
        });
    }
};
