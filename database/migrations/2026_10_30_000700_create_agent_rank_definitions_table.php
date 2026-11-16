<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_rank_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('level')->default(0);
            $table->string('badge_color')->default('grey');
            $table->text('description')->nullable();

            $table->unsignedInteger('min_delivered_orders')->default(0);
            $table->decimal('min_sales_volume', 12, 2)->default(0);
            $table->decimal('min_quality_score', 5, 2)->nullable();
            $table->unsignedInteger('min_active_months')->default(0);

            $table->decimal('bonus_rate', 6, 4)->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_rank_definitions');
    }
};
