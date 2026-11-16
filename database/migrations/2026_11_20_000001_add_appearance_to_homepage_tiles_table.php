<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_tiles', function (Blueprint $table) {
            if (! Schema::hasColumn('homepage_tiles', 'bg_color')) {
                $table->string('bg_color', 20)->nullable()->after('image');
            }

            if (! Schema::hasColumn('homepage_tiles', 'text_color')) {
                $table->string('text_color', 20)->nullable()->after('bg_color');
            }

            if (! Schema::hasColumn('homepage_tiles', 'size')) {
                $table->string('size', 20)->default('md')->after('text_color');
            }

            if (! Schema::hasColumn('homepage_tiles', 'shape')) {
                $table->string('shape', 20)->default('circle')->after('size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('homepage_tiles', function (Blueprint $table) {
            if (Schema::hasColumn('homepage_tiles', 'shape')) {
                $table->dropColumn('shape');
            }

            if (Schema::hasColumn('homepage_tiles', 'size')) {
                $table->dropColumn('size');
            }

            if (Schema::hasColumn('homepage_tiles', 'text_color')) {
                $table->dropColumn('text_color');
            }

            if (Schema::hasColumn('homepage_tiles', 'bg_color')) {
                $table->dropColumn('bg_color');
            }
        });
    }
};
