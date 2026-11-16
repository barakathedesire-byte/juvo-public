<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_rank_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('sales_reps')->cascadeOnDelete();
            $table->foreignId('rank_definition_id')->nullable()->constrained('agent_rank_definitions')->nullOnDelete();
            $table->foreignId('previous_rank_id')->nullable()->constrained('agent_rank_definitions')->nullOnDelete();

            $table->string('period')->nullable();

            $table->decimal('sales_volume', 12, 2)->default(0);
            $table->unsignedInteger('delivered_orders')->default(0);
            $table->decimal('quality_score', 5, 2)->nullable();

            $table->boolean('promoted')->default(false);
            $table->text('reason')->nullable();

            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_rank_histories');
    }
};
