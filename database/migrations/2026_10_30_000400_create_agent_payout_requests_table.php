<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('agent_id')->constrained('sales_reps')->cascadeOnDelete();

            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('TZS');

            $table->string('method')->nullable();
            $table->string('account_name')->nullable();
            $table->text('account_number')->nullable();

            $table->string('status')->default('requested');
            $table->string('provider_reference')->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_payout_requests');
    }
};
