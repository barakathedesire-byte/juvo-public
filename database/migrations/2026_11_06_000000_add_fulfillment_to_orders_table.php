<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','accepted','processing','packed','ready_for_pickup','dispatched','rider_arrived','delivered','completed','canceled','returned') NOT NULL DEFAULT 'pending'");

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'fulfillment_type')) {
                $table->string('fulfillment_type', 16)->default('delivery')->after('payment_method');
            }
            if (! Schema::hasColumn('orders', 'pickup_location_id')) {
                $table->unsignedBigInteger('pickup_location_id')->nullable()->after('fulfillment_type');
                $table->index('pickup_location_id');
            }
            if (! Schema::hasColumn('orders', 'pickup_location_name')) {
                $table->string('pickup_location_name')->nullable()->after('pickup_location_id');
            }
            if (! Schema::hasColumn('orders', 'pickup_ready_at')) {
                $table->timestamp('pickup_ready_at')->nullable()->after('delivered_at');
            }
            if (! Schema::hasColumn('orders', 'rider_arrived_at')) {
                $table->timestamp('rider_arrived_at')->nullable()->after('pickup_ready_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'rider_arrived_at')) {
                $table->dropColumn('rider_arrived_at');
            }
            if (Schema::hasColumn('orders', 'pickup_ready_at')) {
                $table->dropColumn('pickup_ready_at');
            }
            if (Schema::hasColumn('orders', 'pickup_location_name')) {
                $table->dropColumn('pickup_location_name');
            }
            if (Schema::hasColumn('orders', 'pickup_location_id')) {
                $table->dropIndex(['pickup_location_id']);
                $table->dropColumn('pickup_location_id');
            }
            if (Schema::hasColumn('orders', 'fulfillment_type')) {
                $table->dropColumn('fulfillment_type');
            }
        });

        DB::table('orders')->where('status', 'packed')->update(['status' => 'processing']);
        DB::table('orders')->where('status', 'rider_arrived')->update(['status' => 'dispatched']);

        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','accepted','processing','ready_for_pickup','dispatched','delivered','completed','canceled','returned') NOT NULL DEFAULT 'pending'");
    }
};
