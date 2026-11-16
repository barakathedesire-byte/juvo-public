<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_invitations')) {
            return;
        }

        Schema::create('agent_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_sales_rep_id')->constrained('sales_reps')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('token', 80)->unique();
            $table->string('status', 30)->default('pending');
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_sales_rep_id')->nullable()->constrained('sales_reps')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_invitations');
    }
};
