<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_id');
            $table->string('title');
            $table->decimal('total_budget', 12, 2)->default(0);
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('amount_paid')->default(0);
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'active', 'paused'])->default('pending');
            $table->boolean('auto_approved')->default(false);
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
