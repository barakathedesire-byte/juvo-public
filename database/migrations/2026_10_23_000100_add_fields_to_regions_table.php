<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            if (! Schema::hasColumn('regions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('country');
            }
            if (! Schema::hasColumn('regions', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            if (Schema::hasColumn('regions', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('regions', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
