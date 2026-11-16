<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_risk_cases')) {
            Schema::create('logistics_risk_cases', function (Blueprint $table) {
                $table->id();
                $table->string('case_number', 64)->unique();
                $table->string('anomaly_type', 64);
                $table->string('severity', 24);
                $table->string('subject_type', 64)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('risk_score', 8, 2)->nullable();
                $table->json('factors')->nullable();
                $table->string('status', 32)->default('new');
                $table->foreignId('assigned_to')->nullable()->constrained('logistics_admins')->nullOnDelete();
                $table->text('resolution')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'severity']);
                $table->index(['anomaly_type']);
            });
        }

        if (! Schema::hasTable('logistics_risk_evidence')) {
            Schema::create('logistics_risk_evidence', function (Blueprint $table) {
                $table->id();
                $table->foreignId('case_id')->constrained('logistics_risk_cases')->cascadeOnDelete();
                $table->string('evidence_type', 64);
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->string('file_path')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('logistics_admins')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_risk_rules')) {
            Schema::create('logistics_risk_rules', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->string('name');
                $table->string('severity', 24)->default('moderate');
                $table->decimal('threshold', 12, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_risk_evidence');
        Schema::dropIfExists('logistics_risk_cases');
        Schema::dropIfExists('logistics_risk_rules');
    }
};
