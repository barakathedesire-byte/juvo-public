<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_rep_id')->nullable()->constrained('sales_reps')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->default('referral');
            $table->enum('status', ['new', 'contacted', 'qualified', 'won', 'lost'])->default('new');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->timestamps();

            $table->index(['sales_rep_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_leads');
    }
};
