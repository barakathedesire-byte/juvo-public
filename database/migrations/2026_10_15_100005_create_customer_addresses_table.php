<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('label')->default('Home');
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('address');
                $table->string('suite')->nullable();
                $table->string('city');
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->default('Tanzania');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
