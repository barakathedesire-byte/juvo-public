<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            $table->string('actor_type', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            $table->string('actor_type', 32)->nullable()->change();
        });
    }
};
