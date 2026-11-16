<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_status_histories')) {
            return;
        }

        Schema::create('lead_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->constrained('sales_reps')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_status_histories');
    }
};
