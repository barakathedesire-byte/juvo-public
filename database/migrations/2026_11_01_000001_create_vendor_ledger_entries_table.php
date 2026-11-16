<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')
                ->constrained('payments')
                ->onDelete('cascade');
            $table->foreignId('vendor_id')
                ->constrained('vendors')
                ->onDelete('cascade');
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');

            $table->decimal('gross_amount', 12, 2);
            $table->decimal('marketplace_commission', 12, 2)->default(0);
            $table->decimal('net_vendor_earning', 12, 2);

            $table->enum('status', [
                'pending_settlement',
                'cleared',
                'failed',
                'reversed',
            ])->default('pending_settlement');

            $table->json('commission_breakdown')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['payment_id', 'vendor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_ledger_entries');
    }
};
