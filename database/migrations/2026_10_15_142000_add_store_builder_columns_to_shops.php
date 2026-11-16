<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'theme')) {
                $table->string('theme')->default('default');
            }
            if (! Schema::hasColumn('shops', 'layout')) {
                $table->json('layout')->nullable();
            }
            if (! Schema::hasColumn('shops', 'layout_draft')) {
                $table->json('layout_draft')->nullable();
            }
            if (! Schema::hasColumn('shops', 'layout_published_at')) {
                $table->timestamp('layout_published_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            foreach (['theme', 'layout', 'layout_draft', 'layout_published_at'] as $col) {
                if (Schema::hasColumn('shops', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
