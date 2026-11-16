<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('priority', 20)->unique();
            $table->unsignedInteger('first_response_minutes')->default(480);
            $table->unsignedInteger('resolution_minutes')->default(1440);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_sla_policies');
    }
};
