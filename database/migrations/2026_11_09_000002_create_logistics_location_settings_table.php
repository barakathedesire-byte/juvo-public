<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('logistics_location_settings')) {
            return;
        }

        Schema::create('logistics_location_settings', function (Blueprint $table) {
            $table->foreignId('location_id')->primary()->constrained('locations')->cascadeOnDelete();
            $table->boolean('is_covered')->default(true);
            $table->string('delivery_zone')->nullable();
            $table->string('hub_name')->nullable();
            $table->foreignId('assigned_rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->decimal('delivery_fee_override', 12, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('service_notes')->nullable();
            $table->timestamps();

            $table->index(['is_covered']);
            $table->index('assigned_rider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_location_settings');
    }
};
