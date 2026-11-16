<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin portal self-management: appearance/behaviour key-values and a registry
 * of custom sidebar tabs an administrator can add, order and disable without a
 * code deploy. Built-in tabs remain code-defined (see AdminTabRegistry).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_settings')) {
            Schema::create('admin_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group', 40)->default('appearance');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_portal_tabs')) {
            Schema::create('admin_portal_tabs', function (Blueprint $table) {
                $table->id();
                $table->string('label');
                $table->string('icon', 60)->nullable();
                $table->string('url');
                $table->string('group', 40)->default('System');
                $table->string('permission')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_custom')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_portal_tabs');
        Schema::dropIfExists('admin_settings');
    }
};
