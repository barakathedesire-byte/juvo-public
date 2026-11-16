<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_zones')) {
            Schema::create('logistics_zones', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 64)->unique();
                $table->text('description')->nullable();
                $table->json('polygon')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('capacity')->nullable();
                $table->text('service_restrictions')->nullable();
                $table->timestamps();

                $table->index(['is_active']);
            });
        }

        if (! Schema::hasTable('logistics_hubs')) {
            Schema::create('logistics_hubs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 64)->unique();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('zone_id')->nullable()->constrained('logistics_zones')->nullOnDelete();
                $table->string('address')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('operating_hours')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['location_id']);
            });
        }

        if (Schema::hasTable('logistics_zones') && ! Schema::hasColumn('logistics_zones', 'primary_hub_id')) {
            Schema::table('logistics_zones', function (Blueprint $table) {
                $table->foreignId('primary_hub_id')->nullable()->after('polygon')->constrained('logistics_hubs')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('logistics_zone_locations')) {
            Schema::create('logistics_zone_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('zone_id')->constrained('logistics_zones')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['zone_id', 'location_id']);
            });
        }

        if (! Schema::hasTable('logistics_zone_riders')) {
            Schema::create('logistics_zone_riders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('zone_id')->constrained('logistics_zones')->cascadeOnDelete();
                $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['zone_id', 'rider_id']);
            });
        }

        if (! Schema::hasTable('logistics_delivery_pricing_rules')) {
            Schema::create('logistics_delivery_pricing_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('zone_id')->nullable()->constrained('logistics_zones')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('name');
                $table->string('delivery_type', 32)->default('door');
                $table->string('vehicle_type', 32)->nullable();
                $table->decimal('base_fee', 12, 2)->default(0);
                $table->decimal('per_km_fee', 12, 2)->default(0);
                $table->decimal('minimum_fee', 12, 2)->default(0);
                $table->decimal('peak_surcharge', 12, 2)->default(0);
                $table->decimal('handling_surcharge', 12, 2)->default(0);
                $table->decimal('free_delivery_threshold', 12, 2)->nullable();
                $table->json('distance_tiers')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'effective_from', 'effective_to'], 'pricing_effective_idx');
                $table->index(['zone_id', 'location_id']);
            });
        }

        if (! Schema::hasTable('logistics_routes')) {
            Schema::create('logistics_routes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('origin_location_id')->constrained('locations')->cascadeOnDelete();
                $table->foreignId('destination_location_id')->constrained('locations')->cascadeOnDelete();
                $table->decimal('distance_km', 10, 2)->nullable();
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->json('route_geometry')->nullable();
                $table->string('status', 32)->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['origin_location_id', 'destination_location_id'], 'routes_origin_destination_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_routes');
        Schema::dropIfExists('logistics_delivery_pricing_rules');
        Schema::dropIfExists('logistics_zone_riders');
        Schema::dropIfExists('logistics_zone_locations');
        Schema::dropIfExists('logistics_hubs');
        Schema::dropIfExists('logistics_zones');
    }
};
