<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared maker-checker infrastructure used by Finance, Support, Sales,
     * Staff Access Control and Vendor Operations.
     */
    public function up(): void
    {
        Schema::create('approval_policies', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('module')->default('general');
            $table->boolean('requires_approval')->default(true);
            $table->decimal('min_amount', 14, 2)->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->string('approver_permission')->nullable();
            $table->unsignedTinyInteger('stages')->default(1);
            $table->enum('risk', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('policy_key')->nullable();
            $table->string('ability');
            $table->string('module')->default('general');
            $table->nullableMorphs('approvable');
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->string('requester_role')->nullable();
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->enum('risk', ['low', 'medium', 'high'])->default('medium');
            $table->unsignedTinyInteger('required_approvals')->default(1);
            $table->unsignedTinyInteger('received_approvals')->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'changes_requested', 'escalated', 'canceled', 'executed'])
                ->default('pending');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'module']);
            $table->index('requester_id');
            $table->index('assigned_to');
            $table->index('ability');
        });

        Schema::create('approval_request_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('approval_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_policies');
    }
};
