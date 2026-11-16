<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('sales_reps')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_detail_id')->nullable()->constrained('order_details')->nullOnDelete();

            $table->foreignId('policy_id')->nullable()->constrained('agent_commission_policies')->nullOnDelete();
            $table->unsignedInteger('policy_version')->nullable();

            $table->string('role')->default('direct');
            $table->string('basis')->default('merch_after_discount_ex_tax');
            $table->decimal('basis_amount', 12, 2)->default(0);
            $table->decimal('rate', 6, 4)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('TZS');

            $table->string('state')->default('pending');
            $table->string('hold_reason')->nullable();

            $table->foreignId('reversal_of_id')->nullable()->constrained('agent_commission_entries')->nullOnDelete();
            $table->foreignId('payout_request_id')->nullable()->constrained('agent_payout_requests')->nullOnDelete();

            $table->timestamp('eligible_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();

            $table->string('dedup_key')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique('dedup_key');
            $table->index(['agent_id', 'state']);
            $table->index(['agent_id', 'role']);
            $table->index('order_id');
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commission_entries');
    }
};
