<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('region_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('postal_code')->nullable();
                $table->decimal('shipping_fee', 12, 2)->default(0);
                $table->decimal('pickup_fee', 12, 2)->nullable();
                $table->decimal('free_over', 12, 2)->nullable();
                $table->string('eta_label')->nullable();
                $table->string('pickup_eta')->nullable();
                $table->string('door_eta')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
