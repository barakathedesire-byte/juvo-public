<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_events')) {
            Schema::create('integration_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 191)->unique();
                $table->string('event_type', 120);
                $table->string('source', 64);
                $table->string('target', 64)->nullable();
                $table->json('payload')->nullable();
                $table->string('status', 32)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->text('error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
    }
};
