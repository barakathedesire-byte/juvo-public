<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_dispatch_configs')) {
            Schema::create('logistics_dispatch_configs', function (Blueprint $table) {
                $table->id();
                $table->boolean('auto_dispatch_enabled')->default(false);
                $table->unsignedInteger('max_active_assignments')->default(3);
                $table->unsignedInteger('assignment_timeout_minutes')->default(10);
                $table->decimal('minimum_score', 8, 2)->default(40);
                $table->unsignedInteger('batch_size')->default(10);
                $table->json('scoring_weights')->nullable();
                $table->json('eligible_zones')->nullable();
                $table->json('eligible_order_types')->nullable();
                $table->json('vehicle_compatibility')->nullable();
                $table->boolean('require_manual_approval')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_dispatch_assignments')) {
            Schema::create('logistics_dispatch_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('logistics_admins')->nullOnDelete();
                $table->string('assignment_type', 32)->default('manual');
                $table->decimal('score', 8, 2)->nullable();
                $table->json('factors')->nullable();
                $table->string('status', 32)->default('assigned');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('timed_out_at')->nullable();
                $table->foreignId('reassigned_from_id')->nullable()->constrained('logistics_dispatch_assignments')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'status']);
                $table->index(['rider_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_dispatch_assignments');
        Schema::dropIfExists('logistics_dispatch_configs');
    }
};
