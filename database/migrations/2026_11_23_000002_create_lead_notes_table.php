<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_notes')) {
            return;
        }

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->constrained('sales_reps')->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_notes');
    }
};
