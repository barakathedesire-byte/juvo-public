<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_route_plans')) {
            Schema::create('logistics_route_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
                $table->string('status', 32)->default('planned');
                $table->string('optimization_method', 64)->default('fallback_nearest_neighbor');
                $table->decimal('planned_distance_km', 10, 2)->nullable();
                $table->unsignedInteger('planned_duration_minutes')->nullable();
                $table->decimal('actual_distance_km', 10, 2)->nullable();
                $table->unsignedInteger('actual_duration_minutes')->nullable();
                $table->string('provider', 64)->nullable();
                $table->json('provider_response')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['rider_id', 'status']);
            });
        }

        if (! Schema::hasTable('logistics_route_stops')) {
            Schema::create('logistics_route_stops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('route_plan_id')->constrained('logistics_route_plans')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->unsignedInteger('sequence');
                $table->string('stop_type', 32)->default('delivery');
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('status', 32)->default('pending');
                $table->timestamp('planned_arrival_at')->nullable();
                $table->timestamp('actual_arrival_at')->nullable();
                $table->decimal('distance_from_previous_km', 10, 2)->nullable();
                $table->unsignedInteger('duration_from_previous_minutes')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['route_plan_id', 'sequence']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_route_stops');
        Schema::dropIfExists('logistics_route_plans');
    }
};
