<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_slides')) {
            return;
        }

        Schema::table('homepage_slides', function (Blueprint $table) {
            if (! Schema::hasColumn('homepage_slides', 'height')) {
                $table->string('height')->default('medium')->after('text_align');
            }
            if (! Schema::hasColumn('homepage_slides', 'title_size')) {
                $table->unsignedTinyInteger('title_size')->nullable()->after('height');
            }
            if (! Schema::hasColumn('homepage_slides', 'bg_color')) {
                $table->string('bg_color', 32)->nullable()->after('title_size');
            }
            if (! Schema::hasColumn('homepage_slides', 'bg_color_2')) {
                $table->string('bg_color_2', 32)->nullable()->after('bg_color');
            }
            if (! Schema::hasColumn('homepage_slides', 'text_color')) {
                $table->string('text_color', 32)->nullable()->after('bg_color_2');
            }
            if (! Schema::hasColumn('homepage_slides', 'overlay')) {
                $table->string('overlay')->nullable()->after('text_color');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('homepage_slides')) {
            return;
        }

        Schema::table('homepage_slides', function (Blueprint $table) {
            foreach (['height', 'title_size', 'bg_color', 'bg_color_2', 'text_color', 'overlay'] as $column) {
                if (Schema::hasColumn('homepage_slides', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
