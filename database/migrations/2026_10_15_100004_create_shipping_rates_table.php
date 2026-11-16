<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_rates')) {
            Schema::create('shipping_rates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('country')->nullable();
                $table->string('region')->nullable();
                $table->decimal('min_order_value', 12, 2)->default(0);
                $table->decimal('max_order_value', 12, 2)->nullable();
                $table->decimal('rate', 12, 2)->default(0);
                $table->decimal('free_over', 12, 2)->nullable();
                $table->string('eta_label')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
