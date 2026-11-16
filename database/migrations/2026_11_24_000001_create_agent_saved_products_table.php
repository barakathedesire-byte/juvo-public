<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_saved_products')) {
            return;
        }

        Schema::create('agent_saved_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_rep_id')->constrained('sales_reps')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['sales_rep_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_saved_products');
    }
};
