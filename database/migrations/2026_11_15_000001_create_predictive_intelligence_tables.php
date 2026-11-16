<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_predictions')) {
            Schema::create('logistics_predictions', function (Blueprint $table) {
                $table->id();
                $table->string('prediction_type', 64);
                $table->string('subject_type', 64)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('features')->nullable();
                $table->json('result')->nullable();
                $table->decimal('score', 8, 2)->nullable();
                $table->string('risk_level', 24)->nullable();
                $table->decimal('confidence', 5, 2)->nullable();
                $table->timestamp('predicted_at')->nullable();
                $table->timestamp('actual_at')->nullable();
                $table->json('actual_result')->nullable();
                $table->timestamps();

                $table->index(['prediction_type', 'subject_type', 'subject_id'], 'pred_subject_idx');
            });
        }

        if (! Schema::hasTable('logistics_predictive_alerts')) {
            Schema::create('logistics_predictive_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('alert_type', 64);
                $table->string('severity', 24);
                $table->string('subject_type', 64)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->json('factors')->nullable();
                $table->string('recommended_action')->nullable();
                $table->string('status', 32)->default('open');
                $table->foreignId('assigned_to')->nullable()->constrained('logistics_admins')->nullOnDelete();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'severity']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_predictive_alerts');
        Schema::dropIfExists('logistics_predictions');
    }
};
