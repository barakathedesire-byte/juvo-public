<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_payout_request_id')->constrained('agent_payout_requests')->cascadeOnDelete();
            $table->foreignId('agent_commission_entry_id')->nullable()->constrained('agent_commission_entries')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('agent_payout_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_payout_items');
    }
};
