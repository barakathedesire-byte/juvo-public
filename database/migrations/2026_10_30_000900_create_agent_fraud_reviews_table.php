<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_fraud_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('sales_reps')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('type');
            $table->string('severity')->default('low');
            $table->unsignedInteger('score')->default(0);
            $table->json('signals')->nullable();

            $table->string('status')->default('open');
            $table->string('decision')->nullable();
            $table->text('resolution_reason')->nullable();
            $table->json('evidence')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_fraud_reviews');
    }
};
