<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-role decision authority. Each row describes how a role may act on a
     * governed ability: allowed, allowed within limits, requires approval, or
     * prohibited. Thresholds live in `limits` (json) so nothing is hardcoded in
     * the frontend.
     */
    public function up(): void
    {
        Schema::create('role_decision_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('ability');
            $table->enum('mode', ['allowed', 'limited', 'requires_approval', 'prohibited'])->default('allowed');
            $table->json('limits')->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'ability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_decision_policies');
    }
};
