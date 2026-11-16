<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('TZS');
            $table->string('method')->nullable();
            $table->foreignId('payout_method_id')->nullable()->constrained('vendor_payout_methods')->nullOnDelete();
            $table->enum('status', ['requested', 'approved', 'processing', 'paid', 'rejected', 'failed'])
                ->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('vendor_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payout_id')->constrained('vendor_payouts')->cascadeOnDelete();
            $table->foreignId('vendor_transaction_id')->constrained('vendor_transactions')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payout_items');
        Schema::dropIfExists('vendor_payouts');
    }
};
