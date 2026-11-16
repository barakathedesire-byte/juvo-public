<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_sections')) {
            return;
        }

        Schema::table('homepage_sections', function (Blueprint $table) {
            if (! Schema::hasColumn('homepage_sections', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('homepage_sections', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
            }
            if (! Schema::hasColumn('homepage_sections', 'auto_delete')) {
                $table->boolean('auto_delete')->default(false)->after('ends_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('homepage_sections')) {
            return;
        }

        Schema::table('homepage_sections', function (Blueprint $table) {
            if (Schema::hasColumn('homepage_sections', 'auto_delete')) {
                $table->dropColumn('auto_delete');
            }
            if (Schema::hasColumn('homepage_sections', 'ends_at')) {
                $table->dropColumn('ends_at');
            }
            if (Schema::hasColumn('homepage_sections', 'starts_at')) {
                $table->dropColumn('starts_at');
            }
        });
    }
};
