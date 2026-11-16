<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Juvo Logistics fields
            $table->string('logistics_tracking_id')->nullable()->after('tracking_number');
            $table->string('logistics_carrier')->nullable()->after('logistics_tracking_id');
            $table->string('logistics_status')->nullable()->after('logistics_carrier');
            $table->json('logistics_data')->nullable()->after('logistics_status');
            $table->foreignId('assigned_driver_id')->nullable()->constrained('users')->after('logistics_data');
            $table->string('driver_name')->nullable()->after('assigned_driver_id');
            $table->string('driver_phone')->nullable()->after('driver_name');
            $table->decimal('driver_lat', 10, 8)->nullable()->after('driver_phone');
            $table->decimal('driver_lng', 10, 8)->nullable()->after('driver_lat');
            $table->timestamp('picked_up_at')->nullable()->after('driver_lng');
            $table->string('pod_photo_url')->nullable()->after('picked_up_at');
            $table->string('pod_signature_url')->nullable()->after('pod_photo_url');
            $table->boolean('pod_otp_verified')->default(false)->after('pod_signature_url');
            $table->timestamp('pod_delivered_at')->nullable()->after('pod_otp_verified');

            $table->index('logistics_tracking_id');
            $table->index('logistics_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['logistics_tracking_id']);
            $table->dropIndex(['logistics_status']);
            $table->dropConstrainedForeignId('assigned_driver_id');
            $table->dropColumn([
                'logistics_tracking_id',
                'logistics_carrier',
                'logistics_status',
                'logistics_data',
                'assigned_driver_id',
                'driver_name',
                'driver_phone',
                'driver_lat',
                'driver_lng',
                'picked_up_at',
                'pod_photo_url',
                'pod_signature_url',
                'pod_otp_verified',
                'pod_delivered_at',
            ]);
        });
    }
};
