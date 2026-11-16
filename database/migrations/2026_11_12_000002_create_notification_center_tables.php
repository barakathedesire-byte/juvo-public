<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_channels')) {
            Schema::create('notification_channels', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('key', 64)->unique();
                $table->string('type', 32);
                $table->boolean('is_enabled')->default(true);
                $table->string('provider')->nullable();
                $table->json('config')->nullable();
                $table->string('health_status', 32)->default('unknown');
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('key', 120)->unique();
                $table->string('channel', 32);
                $table->string('subject')->nullable();
                $table->text('body');
                $table->json('variables')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_deliveries')) {
            Schema::create('notification_deliveries', function (Blueprint $table) {
                $table->id();
                $table->string('notification_id', 36)->nullable();
                $table->string('recipient_type', 64);
                $table->unsignedBigInteger('recipient_id');
                $table->string('channel', 32);
                $table->string('category', 64)->nullable();
                $table->string('status', 32)->default('queued');
                $table->string('dedup_key', 191)->nullable()->unique();
                $table->text('provider_response')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'channel'], 'nd_status_channel_idx');
                $table->index(['recipient_type', 'recipient_id'], 'nd_recipient_idx');
            });
        }

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->string('recipient_type', 64);
                $table->unsignedBigInteger('recipient_id');
                $table->string('channel', 32);
                $table->string('category', 64);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->unique(['recipient_type', 'recipient_id', 'channel', 'category'], 'np_unique_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_channels');
    }
};
