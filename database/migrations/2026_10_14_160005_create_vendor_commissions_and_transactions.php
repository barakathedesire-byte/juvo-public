<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_detail_id')->nullable()->constrained('order_details')->nullOnDelete();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_rate', 6, 4)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('TZS');
            $table->enum('status', ['pending', 'settled', 'reversed'])->default('pending');
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index('order_id');
        });

        Schema::create('vendor_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->enum('type', ['sale', 'commission', 'fee', 'refund', 'adjustment', 'payout', 'reversal']);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('TZS');
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_transactions');
        Schema::dropIfExists('vendor_commissions');
    }
};
